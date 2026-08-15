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
use Main\Entity\Bucket;
use Main\Enum\BucketStatus;
use Main\Repository\BucketRepository;
use Main\Request\BucketRequest;
use Main\Request\PageRequest;

#[Singleton]
final class BucketService
{
    #[Autowired]
    private BucketRepository $repo;

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
            if ($this->isUniqueViolation($e)) {
                ClientError::throw('Bucket name already taken', HttpCode::CONFLICT);
            }
            throw $e;
        }

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
            if ($this->isUniqueViolation($e)) {
                ClientError::throw('Bucket name already taken', HttpCode::CONFLICT);
            }
            throw $e;
        }

        return BucketRes::from($bucket);
    }

    public function delete(string $id): void
    {
        $this->get($id);
        $this->repo->delete(Qb::eq('id', $id));
    }

    /** UNIQUE-нарушение Postgres (SQLSTATE 23505) в обёртках репозитория. */
    private function isUniqueViolation(\Throwable $e): bool
    {
        for ($cur = $e; $cur !== null; $cur = $cur->getPrevious()) {
            if ($cur instanceof \PDOException && (string) $cur->getCode() === '23505') {
                return true;
            }
        }
        return false;
    }
}
