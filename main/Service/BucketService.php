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
use Main\Dto\BucketCounts;
use Main\Dto\BucketRes;
use Main\Dto\ExtUsage;
use Main\Dto\FolderCounts;
use Main\Dto\GroupCount;
use Main\Dto\PlacementCounts;
use Main\Entity\Bucket;
use Main\Enum\BucketStatus;
use Main\Enum\CacheVisibility;
use Main\Enum\Retention;
use Main\Repository\BlobRepository;
use Main\Repository\BucketRepository;
use Main\Repository\FileEntryRepository;
use Main\Repository\FolderRepository;
use Main\Request\BucketListRequest;
use Main\Request\BucketRequest;
use Main\Request\CachePolicyRequest;
use Main\Request\PageRequest;
use Main\Support\Db;
use Main\Web\BucketView;

#[Singleton]
final class BucketService
{
    #[Autowired]
    private BucketRepository $repo;

    #[Autowired]
    private BucketProvisioner $provisioner;

    #[Autowired]
    private FileEntryRepository $files;

    #[Autowired]
    private BlobRepository $blobs;

    #[Autowired]
    private FolderRepository $folders;

    public function getAll(PageRequest $request): WrapResult
    {
        if ($request->search !== null && $request->search !== '') {
            $like = '%' . $request->search . '%';
            $this->repo->where(Qb::or(
                Qb::like('name', $like, true),
                Qb::like('description', $like, true),
            ));
        }

        return Wrapper::paginator(
            $this->repo->orderBy('created_at DESC'),
            $request->limit,
            $request->page,
            mapper: fn(Bucket $bucket) => BucketRes::from($bucket),
        );
    }

    public function getOne(string $id): BucketRes
    {
        return BucketRes::from($this->get($id));
    }

    /**
     * @return Bucket[]
     */
    public function all(int $limit = 200): array
    {
        return $this->repo->orderBy('created_at DESC')->limit($limit)->findAll();
    }

    public function counts(): BucketCounts
    {
        return $this->repo
            ->select(sprintf(
                'count(*) AS total,'
                . ' count(*) FILTER (WHERE status = %1$d) AS active,'
                . ' count(*) FILTER (WHERE status <> %1$d) AS pending,'
                . ' count(*) FILTER (WHERE quota_bytes > 0'
                . ' AND used_bytes::numeric / quota_bytes >= 0.9) AS full,'
                . ' coalesce(sum(used_bytes), 0)::bigint AS used,'
                . ' coalesce(sum(quota_bytes), 0)::bigint AS quota',
                BucketStatus::ACTIVE->value,
            ))
            ->find(BucketCounts::class) ?? new BucketCounts();
    }

    public function panelPage(BucketListRequest $request): WrapResult
    {
        if ($request->search !== null && $request->search !== '') {
            $like = '%' . $request->search . '%';
            $this->repo->where(Qb::or(
                Qb::like('name', $like, true),
                Qb::like('description', $like, true),
            ));
        }

        $page = Wrapper::paginator(
            $this->repo->orderBy($request->orderBy()),
            $request->limit,
            $request->page,
        );

        $stats = $this->stats(array_column($page->data, 'id'));

        return new WrapResult(
            meta: $page->meta,
            data: array_map(
                fn(Bucket $bucket) => BucketView::from($bucket, $stats[$bucket->id]),
                $page->data,
            ),
        );
    }

    /**
     * @param string[] $bucketIds
     * @return array<string, array{files: int, blobs: int, folders: int}>
     */
    public function stats(array $bucketIds): array
    {
        $stats = [];
        foreach ($bucketIds as $id) {
            $stats[$id] = ['files' => 0, 'blobs' => 0, 'folders' => 0];
        }
        if ($bucketIds === []) {
            return $stats;
        }

        foreach (['files' => $this->files, 'blobs' => $this->blobs, 'folders' => $this->folders] as $key => $repo) {
            $rows = $repo
                ->select('bucket_id, count(*) AS total')
                ->groupBy('bucket_id')
                ->findAllBy(Qb::in('bucket_id', $bucketIds), GroupCount::class);

            foreach ($rows as $row) {
                $stats[$row->bucket_id][$key] = $row->total;
            }
        }

        return $stats;
    }

    /**
     * @return ExtUsage[]
     */
    public function usage(string $bucketId): array
    {
        return $this->blobs->rawFetch(
            sprintf(
                'SELECT coalesce(x.extension, \'\') AS ext, sum(b.size_bytes)::bigint AS bytes,'
                . ' count(*)::int AS total FROM %s b'
                . ' LEFT JOIN LATERAL (SELECT f.extension FROM %s f'
                . ' WHERE f.blob_id = b.id ORDER BY f.id LIMIT 1) x ON true'
                . ' WHERE b.bucket_id = :bucket GROUP BY 1 ORDER BY bytes DESC, total DESC',
                $this->blobs->originTable(),
                $this->files->originTable(),
            ),
            [new CDOBind('bucket', $bucketId)],
            ExtUsage::class,
        );
    }

    public function placement(string $bucketId): PlacementCounts
    {
        $folders = $this->folders->originTable();

        return $this->files
            ->select(sprintf(
                'count(*) FILTER (WHERE folder_id IS NULL) AS root,'
                . ' count(*) FILTER (WHERE EXISTS (SELECT 1 FROM %1$s d'
                . ' WHERE d.id = folder_id AND d.retention = %2$d)) AS in_folders,'
                . ' count(*) FILTER (WHERE EXISTS (SELECT 1 FROM %1$s d'
                . ' WHERE d.id = folder_id AND d.retention <> %2$d)) AS in_temp',
                $folders,
                Retention::NONE->value,
            ))
            ->findBy(Qb::eq('bucket_id', $bucketId), PlacementCounts::class) ?? new PlacementCounts();
    }

    public function folderCounts(string $bucketId): FolderCounts
    {
        return $this->folders
            ->select(sprintf(
                'count(*) AS total, count(*) FILTER (WHERE retention <> %d) AS temp',
                Retention::NONE->value,
            ))
            ->findBy(Qb::eq('bucket_id', $bucketId), FolderCounts::class) ?? new FolderCounts();
    }

    public function setCachePolicy(string $id, CachePolicyRequest $request): BucketRes
    {
        $bucket = $this->get($id);
        $bucket->cache_max_age = $request->maxAge;
        $bucket->cache_visibility = ($request->visibility ?? CacheVisibility::SHARED)->value;
        $bucket->updated_at = date('Y-m-d H:i:s P');

        $this->repo->update(
            [
                'cache_max_age' => $bucket->cache_max_age,
                'cache_visibility' => $bucket->cache_visibility,
                'updated_at' => $bucket->updated_at,
            ],
            Qb::eq('id', $id),
        );

        return BucketRes::from($bucket);
    }

    public function get(string $id): Bucket
    {
        $bucket = $this->repo->findById($id);
        if ($bucket === null) {
            ClientError::throw("Bucket \"{$id}\" does not exist", HttpCode::NOT_FOUND);
        }
        return $bucket;
    }

    public function create(BucketRequest $request): BucketRes
    {
        $now = date('Y-m-d H:i:s P');

        $bucket = new Bucket();
        $bucket->name = $request->name;
        $bucket->description = $request->description;
        $bucket->quota_bytes = $request->quotaBytes;
        $bucket->status = BucketStatus::CREATED->value;
        $bucket->created_at = $now;
        $bucket->updated_at = $now;

        try {
            $bucket->id = $this->repo->insert($bucket);
        } catch (\Throwable $e) {
            if (Db::isUniqueViolation($e)) {
                ClientError::throw('Bucket name is already in use', HttpCode::CONFLICT);
            }
            throw $e;
        }

        $this->provisioner->provision($bucket->id);

        return BucketRes::from($bucket);
    }

    public function update(string $id, BucketRequest $request): BucketRes
    {
        $bucket = $this->get($id);

        if ($request->quotaBytes < $bucket->used_bytes) {
            ClientError::throw(
                "New quota of {$request->quotaBytes} bytes is below the {$bucket->used_bytes} bytes already stored",
                HttpCode::UNPROCESSABLE_ENTITY,
            );
        }

        $bucket->name = $request->name;
        $bucket->description = $request->description;
        $bucket->quota_bytes = $request->quotaBytes;
        $bucket->updated_at = date('Y-m-d H:i:s P');

        try {
            $this->repo->update(
                [
                    'name' => $bucket->name,
                    'description' => $bucket->description,
                    'quota_bytes' => $bucket->quota_bytes,
                    'updated_at' => $bucket->updated_at,
                ],
                Qb::eq('id', $id),
            );
        } catch (\Throwable $e) {
            if (Db::isUniqueViolation($e)) {
                ClientError::throw('Bucket name is already in use', HttpCode::CONFLICT);
            }
            throw $e;
        }

        return BucketRes::from($bucket);
    }

    public function delete(string $id): void
    {
        $this->get($id);
        $this->repo->update(
            ['status' => BucketStatus::PENDING->value, 'updated_at' => date('Y-m-d H:i:s P')],
            Qb::eq('id', $id),
        );
        $this->provisioner->purge($id);
    }
}
