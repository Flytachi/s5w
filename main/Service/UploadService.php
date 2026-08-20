<?php

declare(strict_types=1);

namespace Main\Service;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\Cdo\Qb;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Exception\ClientError;
use Flytachi\Winter\Kernel\Exception\ServerError;
use Main\Dto\FileRes;
use Main\Dto\UploadRes;
use Main\Entity\Upload;
use Main\Enum\ImageFormat;
use Main\Repository\BucketRepository;
use Main\Repository\FolderRepository;
use Main\Repository\UploadRepository;
use Main\Request\FileUploadRequest;
use Main\Request\UploadStartRequest;
use Main\Storage\BlobStore;
use Psr\Log\LoggerInterface;

#[Singleton]
final class UploadService
{
    /** Что советуем клиенту: 8 МиБ — 36 запросов на файл в 300 МБ и 8 МиБ памяти на кусок. */
    public const int CHUNK = 8 * 1024 * 1024;

    private const int CHUNK_MIN = 4 * 1024 * 1024;

    /**
     * Верх для куска. Тело запроса ограничено WebConfiguration::MAX_BODY, и кусок
     * обязан влезать в него с запасом на заголовки — иначе отказ придёт от Swoole
     * обрывом соединения вместо внятного ответа.
     */
    private const int CHUNK_MAX = 16 * 1024 * 1024;

    private const int TTL = 86400;

    /** Свободного места требуем с запасом: staging не виден квоте бакета. */
    private const int DISK_RESERVE = 512 * 1024 * 1024;

    private const string UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    #[Autowired]
    private UploadRepository $repo;

    #[Autowired]
    private BucketRepository $buckets;

    #[Autowired]
    private TrafficMeter $traffic;

    #[Autowired]
    private FolderRepository $folders;

    #[Autowired]
    private FileService $files;

    #[Autowired]
    private BlobStore $store;

    #[Autowired]
    private LoggerInterface $log;

    public function create(string $bucketId, UploadStartRequest $request, string $baseUrl): UploadRes
    {
        $bucket = $this->buckets->findById($bucketId);
        if ($bucket === null) {
            ClientError::throw('Bucket does not exist', HttpCode::NOT_FOUND);
        }

        // Валидируем папку сразу: узнать о её отсутствии после трёх гигабайт обидно.
        $folder = $this->files->resolveFolder($bucketId, $request->folder);

        if ($request->sha256 !== null) {
            $file = $this->files->adopt(
                $bucketId,
                strtolower($request->sha256),
                $request->name,
                $request->toFileRequest(),
                $baseUrl,
            );
            if ($file !== null) {
                return UploadRes::deduplicated($file);
            }
        }

        if ($bucket->used_bytes + $request->size > $bucket->quota_bytes) {
            ClientError::throw(
                sprintf(
                    'Bucket quota exceeded: this upload needs %d bytes, %d of %d bytes are free',
                    $request->size,
                    max(0, $bucket->quota_bytes - $bucket->used_bytes),
                    $bucket->quota_bytes,
                ),
                HttpCode::REQUEST_ENTITY_TOO_LARGE,
            );
        }

        $this->requireDiskSpace($request->size);

        if (!$this->store->uploadRenames()) {
            $this->log->warning(
                'upload staging and blob storage are on different filesystems:'
                . ' finishing an upload will copy the file instead of renaming it',
            );
        }

        $upload = new Upload();
        $upload->bucket_id = $bucketId;
        $upload->folder_id = $folder?->id;
        $upload->name = $request->name;
        $upload->size_bytes = $request->size;
        $upload->sha256 = $request->sha256 === null ? null : strtolower($request->sha256);
        $upload->hash_state = $this->freshHashState();
        $upload->format = $request->format->value;
        $upload->quality = $request->quality;
        $upload->max_width = $request->maxWidth;
        $upload->max_height = $request->maxHeight;
        $upload->expires_at = date('Y-m-d H:i:s P', time() + self::TTL);
        $upload->created_at = date('Y-m-d H:i:s P');
        $upload->updated_at = $upload->created_at;
        $upload->id = $this->repo->insert($upload);

        try {
            $this->store->uploadCreate((string) $upload->id);
        } catch (\Throwable $e) {
            $this->repo->delete(Qb::eq('id', $upload->id));
            throw $e;
        }

        return UploadRes::from($upload, self::CHUNK);
    }

    public function status(string $bucketId, string $uploadId): UploadRes
    {
        return UploadRes::from($this->require($bucketId, $uploadId), self::CHUNK);
    }

    public function append(string $bucketId, string $uploadId, ?int $offset, string $bytes): UploadRes
    {
        $length = strlen($bytes);
        if ($length === 0) {
            ClientError::throw('Chunk is empty', HttpCode::BAD_REQUEST);
        }

        // Канал потрачен независимо от того, чем кончится загрузка: кусок уже принят,
        // а сессию могут и бросить. Именно это входящий трафик и должен показывать —
        // по `used_bytes` брошенная загрузка не видна вовсе.
        $this->traffic->ingress($bucketId, $length);
        if ($length > self::CHUNK_MAX) {
            ClientError::throw(
                sprintf('Chunk is too large: %d bytes, the limit is %d', $length, self::CHUNK_MAX),
                HttpCode::REQUEST_ENTITY_TOO_LARGE,
            );
        }

        $db = $this->repo->db();
        $db->beginTransaction();

        try {
            $upload = $this->lock($bucketId, $uploadId);

            if ($offset !== null && $offset !== $upload->offset_bytes) {
                ClientError::throw(
                    sprintf('Chunk offset %d does not continue this upload at %d', $offset, $upload->offset_bytes),
                    HttpCode::CONFLICT,
                );
            }

            $end = $upload->offset_bytes + $length;
            if ($end > $upload->size_bytes) {
                ClientError::throw(
                    sprintf(
                        'This chunk overruns the declared size: %d bytes sent of %d',
                        $end,
                        $upload->size_bytes,
                    ),
                    HttpCode::REQUEST_ENTITY_TOO_LARGE,
                );
            }
            if ($length < self::CHUNK_MIN && $end !== $upload->size_bytes) {
                ClientError::throw(
                    sprintf('Chunk is too small: %d bytes, the minimum is %d', $length, self::CHUNK_MIN),
                    HttpCode::BAD_REQUEST,
                );
            }

            $context = $this->readHashState($upload);
            hash_update($context, $bytes);

            $written = $this->store->uploadAppend((string) $upload->id, $upload->offset_bytes, $bytes);
            if ($written !== $end) {
                throw new \RuntimeException(
                    "Staging file of upload {$upload->id} is {$written} bytes, expected {$end}",
                );
            }

            $upload->offset_bytes = $end;
            $upload->hash_state = base64_encode(serialize($context));
            $upload->updated_at = date('Y-m-d H:i:s P');
            // Срок отсчитывается от последнего куска, а не от начала: загрузка,
            // которая идёт третьи сутки, живая, и уборщик не должен её трогать.
            $upload->expires_at = date('Y-m-d H:i:s P', time() + self::TTL);

            $this->repo->update(
                [
                    'offset_bytes' => $upload->offset_bytes,
                    'hash_state' => $upload->hash_state,
                    'expires_at' => $upload->expires_at,
                    'updated_at' => $upload->updated_at,
                ],
                Qb::eq('id', $upload->id),
            );

            $db->commit();

            return UploadRes::from($upload, self::CHUNK);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function complete(string $bucketId, string $uploadId, string $baseUrl): FileRes
    {
        $upload = $this->require($bucketId, $uploadId);

        if ($upload->offset_bytes !== $upload->size_bytes) {
            ClientError::throw(
                sprintf(
                    'Upload is not finished: %d bytes of %d received',
                    $upload->offset_bytes,
                    $upload->size_bytes,
                ),
                HttpCode::CONFLICT,
            );
        }

        $path = $this->store->uploadPath((string) $upload->id);
        $onDisk = $this->store->uploadSize((string) $upload->id);
        if ($onDisk !== $upload->size_bytes) {
            $this->discard($upload);
            ClientError::throw(
                sprintf('Upload data is incomplete: %d bytes on disk of %d', $onDisk, $upload->size_bytes),
                HttpCode::CONFLICT,
            );
        }

        $hash = hash_final($this->readHashState($upload));
        if ($upload->sha256 !== null && $hash !== trim($upload->sha256)) {
            $this->discard($upload);
            ClientError::throw('Uploaded data does not match the declared checksum', HttpCode::UNPROCESSABLE_ENTITY);
        }

        try {
            $file = $this->files->ingest(
                $bucketId,
                $path,
                $upload->name,
                $this->toFileRequest($upload),
                $baseUrl,
            );
        } catch (\Throwable $e) {
            // Содержимое уже не восстановить: staging либо уехал, либо испорчен.
            $this->discard($upload);
            throw $e;
        } finally {
            // При обработке картинки в хранилище уезжает не сам staging, а её результат.
            $this->store->uploadDelete((string) $upload->id);
        }

        $this->repo->delete(Qb::eq('id', $upload->id));

        return $file;
    }

    public function abort(string $bucketId, string $uploadId): void
    {
        $this->discard($this->require($bucketId, $uploadId));
    }

    /** @return int число снятых сессий */
    public function purgeExpired(int $limit): int
    {
        $expired = $this->repo
            ->where(Qb::lte('expires_at', date('Y-m-d H:i:s P')))
            ->orderBy('expires_at')
            ->limit($limit)
            ->findAll();

        $removed = 0;

        foreach ($expired as $upload) {
            if ($this->discardIfIdle($upload)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Снимает сессию, если она и под блокировкой осталась просроченной. Кусок,
     * успевший дойти между выборкой и блокировкой, сдвигает срок — такую не трогаем.
     */
    private function discardIfIdle(Upload $candidate): bool
    {
        $db = $this->repo->db();
        $db->beginTransaction();

        try {
            $upload = $this->repo->where(Qb::eq('id', $candidate->id))->forBy('UPDATE')->find();

            if ($upload === null || strtotime($upload->expires_at) > time()) {
                $db->commit();
                return false;
            }

            $this->repo->delete(Qb::eq('id', $upload->id));
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // Файл сносим после фиксации: пережить лишний файл проще, чем строку без файла.
        $this->store->uploadDelete((string) $upload->id);

        return true;
    }

    /**
     * Файлы в staging, за которыми не стоит ни одной сессии: остатки падений между
     * вставкой строки и созданием файла или наоборот.
     *
     * @return int число снятых файлов
     */
    public function purgeStray(int $idleSeconds): int
    {
        $root = $this->store->uploadRoot();
        if (!is_dir($root)) {
            return 0;
        }

        $edge = time() - $idleSeconds;
        $removed = 0;

        foreach (new \FilesystemIterator($root, \FilesystemIterator::SKIP_DOTS) as $entry) {
            /** @var \SplFileInfo $entry */
            // Свежий файл не трогаем вообще: в него прямо сейчас может идти кусок.
            if (!$entry->isFile() || $entry->getMTime() > $edge) {
                continue;
            }

            $name = $entry->getFilename();
            // Имя файла — это идентификатор сессии; всё, что на него не похоже,
            // сессией быть не может, и спрашивать базу не о чем.
            if (preg_match(self::UUID, $name) === 1 && $this->repo->where(Qb::eq('id', $name))->exists()) {
                continue;
            }

            if (@unlink($entry->getPathname())) {
                $removed++;
            }
        }

        return $removed;
    }

    private function discard(Upload $upload): void
    {
        $this->repo->delete(Qb::eq('id', $upload->id));
        $this->store->uploadDelete((string) $upload->id);
    }

    private function require(string $bucketId, string $uploadId): Upload
    {
        $upload = $this->repo->findBy(Qb::and(Qb::eq('id', $uploadId), Qb::eq('bucket_id', $bucketId)));
        if ($upload === null) {
            ClientError::throw('Upload does not exist or has expired', HttpCode::NOT_FOUND);
        }

        return $upload;
    }

    private function lock(string $bucketId, string $uploadId): Upload
    {
        $upload = $this->repo
            ->where(Qb::and(Qb::eq('id', $uploadId), Qb::eq('bucket_id', $bucketId)))
            ->forBy('UPDATE')
            ->find();
        if ($upload === null) {
            ClientError::throw('Upload does not exist or has expired', HttpCode::NOT_FOUND);
        }

        return $upload;
    }

    private function toFileRequest(Upload $upload): FileUploadRequest
    {
        return new FileUploadRequest(
            folder: $this->folderName($upload),
            name: $upload->name,
            format: ImageFormat::from($upload->format),
            quality: $upload->quality,
            maxWidth: $upload->max_width,
            maxHeight: $upload->max_height,
        );
    }

    private function folderName(Upload $upload): ?string
    {
        if ($upload->folder_id === null) {
            return null;
        }

        $folder = $this->folders->findById($upload->folder_id);
        if ($folder === null) {
            ClientError::throw('The folder of this upload no longer exists', HttpCode::UNPROCESSABLE_ENTITY);
        }

        return $folder->name;
    }

    private function freshHashState(): string
    {
        return base64_encode(serialize(hash_init('sha256')));
    }

    private function readHashState(Upload $upload): \HashContext
    {
        $raw = base64_decode($upload->hash_state, true);
        $context = $raw === false ? false : @unserialize($raw);

        if (!$context instanceof \HashContext) {
            throw new \RuntimeException("Checksum state of upload {$upload->id} is unreadable");
        }

        return $context;
    }

    private function requireDiskSpace(int $size): void
    {
        $root = $this->store->uploadRoot();
        $free = @disk_free_space(is_dir($root) ? $root : dirname($root));
        if (!is_float($free)) {
            return;
        }

        if ($size + self::DISK_RESERVE > $free) {
            ServerError::throw(
                'Not enough free space on the server for this upload',
                HttpCode::INSUFFICIENT_STORAGE,
            );
        }
    }
}
