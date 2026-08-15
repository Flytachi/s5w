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
use Main\Dto\FolderRes;
use Main\Entity\Folder;
use Main\Enum\BucketStatus;
use Main\Repository\FolderRepository;
use Main\Request\CachePolicyRequest;
use Main\Request\FolderRequest;
use Main\Request\PageRequest;
use Main\Support\Db;

#[Singleton]
final class FolderService
{
    #[Autowired]
    private FolderRepository $repo;

    #[Autowired]
    private BucketService $buckets;

    public function getAll(string $bucketId, PageRequest $request): WrapResult
    {
        $where = [Qb::eq('bucket_id', $bucketId)];
        if ($request->search !== null && $request->search !== '') {
            $where[] = Qb::like('name', '%' . $request->search . '%');
        }

        return Wrapper::paginator(
            $this->repo->where(Qb::and(...$where))->orderBy('name'),
            $request->limit,
            $request->page,
            mapper: fn(Folder $folder) => FolderRes::from($folder),
        );
    }

    public function getOne(string $bucketId, string $name): FolderRes
    {
        return FolderRes::from($this->get($bucketId, $name));
    }

    public function get(string $bucketId, string $name): Folder
    {
        $folder = $this->repo->findBy(Qb::and(
            Qb::eq('bucket_id', $bucketId),
            Qb::eq('name', $name),
        ));
        if ($folder === null) {
            ClientError::throw("Folder «{$name}» not found", HttpCode::NOT_FOUND);
        }
        return $folder;
    }

    public function create(string $bucketId, FolderRequest $request): FolderRes
    {
        $this->requireActiveBucket($bucketId);

        $now = date('Y-m-d H:i:s P');

        $folder = new Folder();
        $folder->bucket_id = $bucketId;
        $folder->name = $request->name;
        $folder->public = $request->public;
        $folder->retention = $request->retention->value;
        $folder->created_at = $now;
        $folder->updated_at = $now;

        try {
            $folder->id = $this->repo->insert($folder);
        } catch (\Throwable $e) {
            if (Db::isUniqueViolation($e)) {
                ClientError::throw("Folder «{$request->name}» already exists", HttpCode::CONFLICT);
            }
            throw $e;
        }

        return FolderRes::from($folder);
    }

    /**
     * Переименование, видимость и срок хранения — одной операцией: все три
     * свойства всегда приходят целиком, поэтому частичных состояний не бывает.
     *
     * TODO (когда появятся файлы): смена public — переписать денормализованный
     * флаг на файлах папки и сбросить кэш отдачи; смена retention — пересчитать
     * expires_at от created_at файлов.
     */
    public function update(string $bucketId, string $name, FolderRequest $request): FolderRes
    {
        $folder = $this->get($bucketId, $name);

        $folder->name = $request->name;
        $folder->public = $request->public;
        $folder->retention = $request->retention->value;
        $folder->updated_at = date('Y-m-d H:i:s P');

        try {
            $this->repo->update(
                [
                    'name' => $folder->name,
                    'public' => $folder->public,
                    'retention' => $folder->retention,
                    'updated_at' => $folder->updated_at,
                ],
                Qb::eq('id', $folder->id),
            );
        } catch (\Throwable $e) {
            if (Db::isUniqueViolation($e)) {
                ClientError::throw("Folder «{$request->name}» already exists", HttpCode::CONFLICT);
            }
            throw $e;
        }

        return FolderRes::from($folder);
    }

    public function setCachePolicy(string $bucketId, string $name, CachePolicyRequest $request): FolderRes
    {
        $folder = $this->get($bucketId, $name);

        $folder->cache_max_age = $request->maxAge;
        $folder->cache_visibility = $request->visibility?->value;
        $folder->updated_at = date('Y-m-d H:i:s P');

        $this->repo->update(
            [
                'cache_max_age' => $folder->cache_max_age,
                'cache_visibility' => $folder->cache_visibility,
                'updated_at' => $folder->updated_at,
            ],
            Qb::eq('id', $folder->id),
        );

        return FolderRes::from($folder);
    }

    /**
     * TODO (когда появятся файлы): удаление папки должно снести и файлы внутри
     * через общий путь удаления файла — иначе CASCADE унесёт строки файлов, а
     * ref_count блобов и квота бакета останутся завышенными.
     */
    public function delete(string $bucketId, string $name): void
    {
        $folder = $this->get($bucketId, $name);
        $this->repo->delete(Qb::eq('id', $folder->id));
    }

    private function requireActiveBucket(string $bucketId): void
    {
        $bucket = $this->buckets->get($bucketId);
        if ($bucket->status !== BucketStatus::ACTIVE->value) {
            ClientError::throw(
                'Bucket is not active: ' . BucketStatus::from($bucket->status)->name,
                HttpCode::CONFLICT,
            );
        }
    }
}
