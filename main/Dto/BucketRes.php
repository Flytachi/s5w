<?php

declare(strict_types=1);

namespace Main\Dto;

use Main\Entity\Bucket;
use Main\Enum\BucketStatus;
use Main\Enum\CacheVisibility;

final class BucketRes
{
    /**
     * @param array{quota: int, used: int, free: int} $bytes
     * @param array{id: int, name: string} $status
     * @param array{maxAge: int|null, visibility: array{id: int, name: string}} $cache
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public array $bytes,
        public array $status,
        public array $cache,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function from(Bucket $model): self
    {
        return new self(
            id: $model->id,
            name: $model->name,
            description: $model->description,
            bytes: [
                'quota' => $model->quota_bytes,
                'used' => $model->used_bytes,
                'free' => max(0, $model->quota_bytes - $model->used_bytes),
            ],
            status: BucketStatus::from($model->status)->toArray(),
            cache: [
                'maxAge' => $model->cache_max_age,
                'visibility' => CacheVisibility::from($model->cache_visibility)->toArray(),
            ],
            createdAt: $model->created_at,
            updatedAt: $model->updated_at,
        );
    }
}
