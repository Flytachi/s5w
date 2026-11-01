<?php

declare(strict_types=1);

namespace Main\Web;

use Main\Entities\Folder;
use Main\Enum\CacheVisibility;
use Main\Enum\Retention;

final readonly class FolderView
{
    public function __construct(
        public string $name,
        public bool $public,
        public int $retentionId,
        public string $retention,
        public ?int $cacheMaxAge,
        public ?CacheVisibility $cacheVisibility,
        public int $files,
        public string $createdAt,
    ) {
    }

    public static function from(Folder $folder, int $files): self
    {
        return new self(
            name: $folder->name,
            public: $folder->public,
            retentionId: $folder->retention,
            retention: Retention::from($folder->retention)->name,
            cacheMaxAge: $folder->cache_max_age,
            cacheVisibility: $folder->cache_visibility === null
                ? null
                : CacheVisibility::from($folder->cache_visibility),
            files: $files,
            createdAt: $folder->created_at,
        );
    }

    public function hasRetention(): bool
    {
        return $this->retention !== Retention::NONE->name;
    }

    public function hasCache(): bool
    {
        return $this->cacheMaxAge !== null || $this->cacheVisibility !== null;
    }

    public function cacheLabel(): string
    {
        $parts = [];
        if ($this->cacheVisibility !== null) {
            $parts[] = $this->cacheVisibility->label();
        }
        if ($this->cacheMaxAge !== null) {
            $parts[] = $this->cacheMaxAge . ' s';
        }

        return implode(' · ', $parts);
    }

    public function retentionLabel(): string
    {
        return match ($this->retention) {
            'DAY' => 'a day',
            'WEEK' => 'a week',
            'MONTH' => 'a month',
            'QUARTER' => 'three months',
            'HALF_YEAR' => 'half a year',
            'YEAR' => 'a year',
            default => 'no expiry',
        };
    }

    public function retentionKey(): string
    {
        return 'retention.' . $this->retention;
    }
}
