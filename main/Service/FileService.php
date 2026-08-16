<?php

declare(strict_types=1);

namespace Main\Service;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\Cdo\Qb;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Exception\ClientError;
use Flytachi\Winter\Kernel\Unit\Pagination\WrapResult;
use Flytachi\Winter\Kernel\Unit\Wrapper;
use Main\Dto\FileRes;
use Main\Entity\Blob;
use Main\Entity\FileEntry;
use Main\Entity\Folder;
use Main\Enum\Retention;
use Main\Image\ImageProcessor;
use Main\Repository\BlobRepository;
use Main\Repository\FileEntryRepository;
use Main\Repository\FolderRepository;
use Main\Request\FileListRequest;
use Main\Request\FilePlacementRequest;
use Main\Request\FileUploadRequest;
use Main\Storage\BlobStore;
use Main\Support\ContentType;
use Main\Support\Db;
use Main\Support\Slug;

#[Singleton]
final class FileService
{
    private const int NAME_ATTEMPTS = 10;

    #[Autowired]
    private FileEntryRepository $repo;

    #[Autowired]
    private BlobRepository $blobRepo;

    #[Autowired]
    private FolderRepository $folderRepo;

    #[Autowired]
    private BlobService $blobs;

    #[Autowired]
    private ImageProcessor $images;

    #[Autowired]
    private BlobStore $store;

    public function upload(
        string $bucketId,
        array $upload,
        FileUploadRequest $request,
        string $baseUrl,
    ): FileRes {
        $tmpPath = $upload['tmp_name'] ?? null;
        if (!is_string($tmpPath) || !is_file($tmpPath)) {
            ClientError::throw('Uploaded file is missing', HttpCode::BAD_REQUEST);
        }

        $folder = $this->resolveFolder($bucketId, $request->folder);
        $sourceName = (string) ($upload['name'] ?? '');

        // До транзакции и до блоба: в хранилище уходят уже обработанные байты,
        // значит и хэш, и квота считаются по ним (docs/plan.md §10.2).
        $processed = $this->images->process($tmpPath, $request);

        $db = $this->repo->db();
        $db->beginTransaction();
        $stored = null;

        try {
            $stored = $this->blobs->store($bucketId, $processed->path, $sourceName);
            $blob = $stored->blob;

            $file = new FileEntry();
            $file->bucket_id = $bucketId;
            $file->folder_id = $folder?->id;
            $file->blob_id = $blob->id;
            $file->mime_type = $stored->mimeType;
            $file->extension = $stored->extension;
            $file->name = $this->finalName($request->name, $sourceName, $stored->extension);
            $file->public = $folder === null ? true : $folder->public;
            $file->expires_at = $this->expiryFor($folder);
            $file->created_at = date('Y-m-d H:i:s P');
            $file->updated_at = $file->created_at;

            $this->insertWithRetries($file);

            $db->commit();

            return FileRes::from($file, $blob, $folder?->name, $baseUrl, $stored->deduplicated, $processed);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // Откат вернул ref_count назад, а файл на диске остался. Убираем его
            // только если байты записали мы: при дедупликации по этому пути
            // лежит содержимое чужой, живой строки.
            if ($stored !== null && !$stored->deduplicated) {
                $this->store->blobDelete($bucketId, $stored->blob->hash);
            }
            throw $e;
        } finally {
            // Результат обработки — наш временный файл рядом с загруженным;
            // за исходником уберёт рантайм, за этим убирать некому.
            if ($processed->temporary) {
                @unlink($processed->path);
            }
        }
    }

    public function getAll(string $bucketId, FileListRequest $request, string $baseUrl): WrapResult
    {
        $where = [Qb::eq('bucket_id', $bucketId)];

        // null — весь бакет, '' — только корень, иначе конкретная папка.
        if ($request->folder !== null) {
            $where[] = $request->folder === ''
                ? Qb::isNull('folder_id')
                : Qb::eq('folder_id', $this->requireFolder($bucketId, $request->folder)->id);
        }
        if ($request->search !== null && $request->search !== '') {
            $where[] = Qb::like('name', '%' . $request->search . '%');
        }

        $page = Wrapper::paginator(
            $this->repo->where(Qb::and(...$where))->orderBy('created_at DESC'),
            $request->limit,
            $request->page,
        );

        if ($page->data === []) {
            return $page;
        }

        // Блобы и папки — двумя IN-запросами, а не по одному на строку:
        // страница в 100 файлов иначе стоит 200 обращений к базе.
        $blobs = $this->mapById($this->blobRepo, array_column($page->data, 'blob_id'));
        $folders = $this->mapById($this->folderRepo, array_filter(array_column($page->data, 'folder_id')));

        return new WrapResult(
            meta: $page->meta,
            data: array_map(
                fn(FileEntry $file) => FileRes::from(
                    $file,
                    $blobs[$file->blob_id] ?? throw new \RuntimeException("Blob missing for file {$file->id}"),
                    $file->folder_id === null ? null : ($folders[$file->folder_id]->name ?? null),
                    $baseUrl,
                ),
                $page->data,
            ),
        );
    }

    public function getOne(string $bucketId, string $slug, string $baseUrl): FileRes
    {
        $file = $this->get($bucketId, $slug);

        return FileRes::from($file, $this->blobOf($file), $this->folderNameOf($file), $baseUrl);
    }

    public function get(string $bucketId, string $slug): FileEntry
    {
        $file = $this->repo->findBy(Qb::and(
            Qb::eq('slug', $slug),
            Qb::eq('bucket_id', $bucketId),
        ));
        if ($file === null) {
            ClientError::throw('File not found', HttpCode::NOT_FOUND);
        }
        return $file;
    }

    /**
     * Переименование и перемещение.
     *
     * При смене папки видимость и срок хранения пересчитываются по новому
     * месту: файл, уехавший из публичной папки в приватную, обязан перестать
     * отдаваться через /o немедленно, а не когда-нибудь.
     */
    public function update(
        string $bucketId,
        string $slug,
        FilePlacementRequest $request,
        string $baseUrl,
    ): FileRes {
        $file = $this->get($bucketId, $slug);
        $folder = $this->resolveFolder($bucketId, $request->folder);

        $file->name = $request->name;
        if ($folder?->id !== $file->folder_id) {
            $file->folder_id = $folder?->id;
            $file->public = $folder === null ? true : $folder->public;
            $file->expires_at = $this->expiryFor($folder);
        }
        $file->updated_at = date('Y-m-d H:i:s P');

        try {
            $this->repo->update(
                [
                    'name' => $file->name,
                    'folder_id' => $file->folder_id,
                    'public' => $file->public,
                    'expires_at' => $file->expires_at,
                    'updated_at' => $file->updated_at,
                ],
                Qb::eq('id', $file->id),
            );
        } catch (\Throwable $e) {
            if (Db::isUniqueViolation($e)) {
                ClientError::throw("Name «{$request->name}» is taken here", HttpCode::CONFLICT);
            }
            throw $e;
        }

        return FileRes::from($file, $this->blobOf($file), $folder?->name, $baseUrl);
    }

    /**
     * Удаление файла со снятием ссылки с содержимого.
     *
     * Строка и счётчик ссылок меняются в одной транзакции; файл на диске
     * удаляется после коммита — если бы порядок был обратным, откат оставил бы
     * живую строку без содержимого.
     */
    public function delete(string $bucketId, string $slug): void
    {
        $file = $this->get($bucketId, $slug);

        $db = $this->repo->db();
        $db->beginTransaction();

        try {
            $this->repo->delete(Qb::eq('id', $file->id));
            $orphan = $this->blobs->release($bucketId, $file->blob_id);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        if ($orphan !== null) {
            $this->store->blobDelete($orphan->bucket_id, $orphan->hash);
        }
    }

    /**
     * Удаление всех файлов папки — через общий путь, чтобы ссылки на блобы и
     * квота бакета считались так же, как при поштучном удалении.
     */
    public function deleteByFolder(string $bucketId, int $folderId): int
    {
        $files = $this->repo->findAllBy(Qb::and(
            Qb::eq('bucket_id', $bucketId),
            Qb::eq('folder_id', $folderId),
        ));

        foreach ($files as $file) {
            $this->delete($bucketId, $file->slug);
        }

        return count($files);
    }

    // ── Внутреннее ───────────────────────────────────────────────────────────

    /**
     * Вставка с двумя видами конфликтов: занятое имя и (теоретически) занятый
     * slug. Какой именно сработал — видно по имени индекса, поэтому суффикс
     * навешивается только на имя, а slug просто перевыпускается.
     *
     * SAVEPOINT обязателен: в Postgres любая ошибка в транзакции делает её
     * непригодной целиком, и без него первая же коллизия имени убила бы уже
     * записанный блоб.
     */
    private function insertWithRetries(FileEntry $file): void
    {
        $db = $this->repo->db();
        $base = $file->name;
        $file->slug = Slug::generate();

        for ($attempt = 1; $attempt <= self::NAME_ATTEMPTS; $attempt++) {
            $db->exec('SAVEPOINT s5w_file');
            try {
                $file->id = $this->repo->insert($file);
                $db->exec('RELEASE SAVEPOINT s5w_file');
                return;
            } catch (\Throwable $e) {
                if (!Db::isUniqueViolation($e)) {
                    throw $e;
                }
                $db->exec('ROLLBACK TO SAVEPOINT s5w_file');

                if (str_contains((string) Db::uniqueConstraint($e), 'slug')) {
                    $file->slug = Slug::generate();
                    continue;
                }
                $file->name = $this->suffixed($base, $attempt);
            }
        }

        ClientError::throw('Name conflict, retry limit exceeded', HttpCode::CONFLICT);
    }

    /** «отчёт.pdf» + 2 → «отчёт (2).pdf» */
    private function suffixed(string $name, int $n): string
    {
        $dot = strrpos($name, '.');
        return $dot === false
            ? "{$name} ({$n})"
            : substr($name, 0, $dot) . " ({$n})" . substr($name, $dot);
    }

    /**
     * Имя из формы, иначе из multipart, иначе случайное — и всегда с
     * расширением по содержимому: без него скачанный файл система не откроет.
     */
    private function finalName(?string $requested, string $sourceName, string $extension): string
    {
        $name = trim((string) ($requested ?: $sourceName));
        if ($name === '') {
            $name = 'file-' . bin2hex(random_bytes(4));
        }
        // Имя не должно уезжать в подкаталоги — оно попадает в
        // Content-Disposition при отдаче.
        $name = str_replace(['/', '\\', "\0"], '-', $name);

        if ($extension === '') {
            return $name;
        }

        $current = ContentType::extensionOf($name);
        if (ContentType::sameExtension($current, $extension)) {
            return $name;
        }

        // Расширение противоречит содержимому: photo.jpg после перекодирования
        // в webp, mislabeled.png с jpeg внутри. Заменяем его, но только если
        // это действительно указание типа — «отчёт v1.2» тоже кончается точкой
        // с хвостом, и его резать нельзя.
        $dot = strrpos($name, '.');
        if ($dot > 0 && ContentType::isKnownExtension($current)) {
            $name = substr($name, 0, $dot);
        }

        return $name . '.' . $extension;
    }

    /** null/'' — корень бакета; иначе папка обязана существовать. */
    private function resolveFolder(string $bucketId, ?string $name): ?Folder
    {
        if ($name === null || $name === '') {
            return null;
        }
        return $this->requireFolder($bucketId, $name);
    }

    private function requireFolder(string $bucketId, string $name): Folder
    {
        $folder = $this->folderRepo->findBy(Qb::and(
            Qb::eq('bucket_id', $bucketId),
            Qb::eq('name', $name),
        ));
        if ($folder === null) {
            ClientError::throw("Folder «{$name}» not found", HttpCode::UNPROCESSABLE_ENTITY);
        }
        return $folder;
    }

    /** Срок жизни файла: скользящий, от момента загрузки (docs/plan.md §5). */
    private function expiryFor(?Folder $folder): ?string
    {
        $interval = $folder === null ? null : Retention::from($folder->retention)->interval();
        if ($interval === null) {
            return null;
        }
        return (new \DateTimeImmutable())->add($interval)->format('Y-m-d H:i:s P');
    }

    private function blobOf(FileEntry $file): Blob
    {
        $blob = $this->blobRepo->findById($file->blob_id);
        if ($blob === null) {
            throw new \RuntimeException("Blob missing for file {$file->id}");
        }
        return $blob;
    }

    private function folderNameOf(FileEntry $file): ?string
    {
        if ($file->folder_id === null) {
            return null;
        }
        return $this->folderRepo->findById($file->folder_id)?->name;
    }

    /**
     * @param int[] $ids
     * @return array<int, object>
     */
    private function mapById(object $repo, array $ids): array
    {
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }

        $map = [];
        foreach ($repo->findAllBy(Qb::in('id', $ids)) as $row) {
            $map[$row->id] = $row;
        }
        return $map;
    }
}
