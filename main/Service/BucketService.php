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
use Main\Dto\BucketRes;
use Main\Dto\GroupCount;
use Main\Entity\Bucket;
use Main\Enum\BucketStatus;
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
                Qb::like('name', $like),
                Qb::like('description', $like),
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
     * Все бакеты для панели — список и переключатель в боковом меню.
     * Возвращает сущности, а не DTO: панели нужны ещё и счётчики, которых в
     * ответе API нет и быть не должно.
     *
     * @return Bucket[]
     */
    public function all(int $limit = 200): array
    {
        return $this->repo->orderBy('created_at DESC')->limit($limit)->findAll();
    }

    /**
     * Страница бакетов для панели: поиск, сортировка и счётчики.
     *
     * Счётчики считаются только для строк этой страницы — по ним не сортируем,
     * поэтому и знать их для всей таблицы незачем.
     */
    public function panelPage(BucketListRequest $request): WrapResult
    {
        if ($request->search !== null && $request->search !== '') {
            $like = '%' . $request->search . '%';
            $this->repo->where(Qb::or(
                Qb::like('name', $like, insensitive: true),
                Qb::like('description', $like, insensitive: true),
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
     * Счётчики содержимого по бакетам: файлы, блобы, папки.
     *
     * Три сгруппированных запроса вместо трёх на каждую строку списка —
     * иначе страница из двадцати бакетов стоила бы шестьдесят обращений.
     *
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
     * Политика кэша по умолчанию для файлов бакета.
     * Папка может её переопределить, файл — нет (docs/plan.md §6).
     */
    public function setCachePolicy(string $id, CachePolicyRequest $request): BucketRes
    {
        $bucket = $this->get($id);
        $bucket->cache_max_age = $request->maxAge;
        $bucket->cache_visibility = $request->visibility?->value;
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
            ClientError::throw("Bucket {$id} not found", HttpCode::NOT_FOUND);
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
                ClientError::throw('Bucket name already taken', HttpCode::CONFLICT);
            }
            throw $e;
        }

        // Каталог создаётся фоном: ответ уходит сразу, статус доедет до ACTIVE сам.
        $this->provisioner->provision($bucket->id);

        return BucketRes::from($bucket);
    }

    public function update(string $id, BucketRequest $request): BucketRes
    {
        $bucket = $this->get($id);

        if ($request->quotaBytes < $bucket->used_bytes) {
            ClientError::throw(
                "Quota {$request->quotaBytes} is below used {$bucket->used_bytes}",
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
                ClientError::throw('Bucket name already taken', HttpCode::CONFLICT);
            }
            throw $e;
        }

        return BucketRes::from($bucket);
    }

    /**
     * Запрос только помечает бакет; строку и каталог уносит фоновая задача.
     * PENDING виден в списке и на нём же отсекаются загрузки в удаляемый бакет.
     */
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
