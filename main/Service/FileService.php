<?php

declare(strict_types=1);

namespace Main\Service;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\Cdo\CDOBind;
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
            if ($stored !== null && !$stored->deduplicated) {
                $this->store->blobDelete($bucketId, $stored->blob->hash);
            }
            throw $e;
        } finally {
            if ($processed->temporary) {
                @unlink($processed->path);
            }
        }
    }

    public function getAll(string $bucketId, FileListRequest $request, string $baseUrl): WrapResult
    {
        $where = [Qb::eq('bucket_id', $bucketId)];

        if ($request->folder !== null) {
            $where[] = $request->folder === ''
                ? Qb::isNull('folder_id')
                : Qb::eq('folder_id', $this->requireFolder($bucketId, $request->folder)->id);
        }
        if ($request->search !== null && $request->search !== '') {
            $where[] = Qb::like('name', '%' . $request->search . '%');
        }

        $page = Wrapper::paginator(
            $this->repo->where(Qb::and(...$where))->orderBy($this->orderBy($request)),
            $request->limit,
            $request->page,
        );

        if ($page->data === []) {
            return $page;
        }

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

    private function orderBy(FileListRequest $request): string
    {
        $dir = $request->dir === 'asc' ? 'ASC' : 'DESC';

        return match ($request->sort) {
            'name' => "name {$dir}",
            'type' => "mime_type {$dir}, name ASC",
            'size' => sprintf(
                '(SELECT b.size_bytes FROM %s b WHERE b.id = blob_id) %s, name ASC',
                $this->blobRepo->originTable(),
                $dir,
            ),
            default => "created_at {$dir}, id {$dir}",
        };
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

    public function delete(string $bucketId, string $slug): void
    {
        $this->remove($this->get($bucketId, $slug));
    }

    public function remove(FileEntry $file): void
    {
        $db = $this->repo->db();
        $db->beginTransaction();

        try {
            $this->repo->delete(Qb::eq('id', $file->id));
            $orphan = $this->blobs->release($file->bucket_id, $file->blob_id);
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

    public function setPublicByFolder(string $bucketId, int $folderId, bool $public): int
    {
        return (int) $this->repo->update(
            ['public' => $public, 'updated_at' => date('Y-m-d H:i:s P')],
            Qb::and(Qb::eq('bucket_id', $bucketId), Qb::eq('folder_id', $folderId)),
        );
    }

    public function resetExpiryByFolder(string $bucketId, int $folderId, Retention $retention): int
    {
        $interval = $retention->sqlInterval();
        $expiry = $interval === null ? 'NULL' : "created_at + interval '{$interval}'";

        $updated = $this->repo->rawFetch(
            sprintf(
                'UPDATE %s SET expires_at = %s, updated_at = :updated'
                . ' WHERE bucket_id = :bucket AND folder_id = :folder RETURNING id',
                $this->repo->originTable(),
                $expiry,
            ),
            [
                new CDOBind('updated', date('Y-m-d H:i:s P')),
                new CDOBind('bucket', $bucketId),
                new CDOBind('folder', $folderId),
            ],
            FileEntry::class,
        );

        return count($updated);
    }

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

    private function suffixed(string $name, int $n): string
    {
        $dot = strrpos($name, '.');
        return $dot === false
            ? "{$name} ({$n})"
            : substr($name, 0, $dot) . " ({$n})" . substr($name, $dot);
    }

    private function finalName(?string $requested, string $sourceName, string $extension): string
    {
        $name = trim((string) ($requested ?: $sourceName));
        if ($name === '') {
            $name = 'file-' . bin2hex(random_bytes(4));
        }
        $name = str_replace(['/', '\\', "\0"], '-', $name);

        if ($extension === '') {
            return $name;
        }

        $current = ContentType::extensionOf($name);
        if (ContentType::sameExtension($current, $extension)) {
            return $name;
        }

        $dot = strrpos($name, '.');
        if ($dot > 0 && ContentType::isKnownExtension($current)) {
            $name = substr($name, 0, $dot);
        }

        return $name . '.' . $extension;
    }

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
