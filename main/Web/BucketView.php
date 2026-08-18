<?php

declare(strict_types=1);

namespace Main\Web;

use Main\Entity\Bucket;
use Main\Enum\BucketStatus;
use Main\Enum\CacheVisibility;

final readonly class BucketView
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public int $quota,
        public int $used,
        public int $free,
        public string $status,
        public ?int $cacheMaxAge,
        public CacheVisibility $cacheVisibility,
        public int $files,
        public int $blobs,
        public int $folders,
        public string $createdAt,
    ) {
    }

    /**
     * @param array{files: int, blobs: int, folders: int} $stats
     */
    public static function from(Bucket $bucket, array $stats): self
    {
        return new self(
            id: $bucket->id,
            name: $bucket->name,
            description: $bucket->description,
            quota: $bucket->quota_bytes,
            used: $bucket->used_bytes,
            free: max(0, $bucket->quota_bytes - $bucket->used_bytes),
            status: BucketStatus::from($bucket->status)->name,
            cacheMaxAge: $bucket->cache_max_age,
            cacheVisibility: CacheVisibility::from($bucket->cache_visibility),
            files: $stats['files'],
            blobs: $stats['blobs'],
            folders: $stats['folders'],
            createdAt: $bucket->created_at,
        );
    }

    public function isActive(): bool
    {
        return $this->status === BucketStatus::ACTIVE->name;
    }

    public function percent(): float
    {
        return $this->quota > 0 ? min(100, $this->used / $this->quota * 100) : 0.0;
    }

    public function quotaState(): string
    {
        return match (true) {
            $this->percent() >= 90 => 'is-danger',
            $this->percent() >= 70 => 'is-warn',
            default => '',
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'ACTIVE' => 'ok',
            'CREATED', 'PENDING' => 'warn',
            default => 'mute',
        };
    }
}
