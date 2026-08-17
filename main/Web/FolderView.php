<?php

declare(strict_types=1);

namespace Main\Web;

use Main\Entity\Folder;
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
        public ?string $cacheVisibility,
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
                : CacheVisibility::from($folder->cache_visibility)->name,
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
            $parts[] = match ($this->cacheVisibility) {
                'PUBLIC' => 'публичный',
                'PRIVATE' => 'приватный',
                default => 'не хранить',
            };
        }
        if ($this->cacheMaxAge !== null) {
            $parts[] = $this->cacheMaxAge . ' с';
        }

        return implode(' · ', $parts);
    }

    public function retentionLabel(): string
    {
        return match ($this->retention) {
            'DAY' => 'день',
            'WEEK' => 'неделя',
            'MONTH' => 'месяц',
            'QUARTER' => 'три месяца',
            'HALF_YEAR' => 'полгода',
            'YEAR' => 'год',
            default => 'без срока',
        };
    }
}
