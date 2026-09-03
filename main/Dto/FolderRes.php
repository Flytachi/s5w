<?php

declare(strict_types=1);

namespace Main\Dto;

use Main\Entity\Folder;
use Main\Enum\CacheVisibility;
use Main\Enum\Retention;

final class FolderRes
{
    /**
     * @param array{id: int, name: string} $retention
     * @param array{maxAge: int|null, visibility: array{id: int, name: string}|null} $cache
     */
    public function __construct(
        public string $name,
        public bool $public,
        public array $retention,
        public array $cache,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function from(Folder $model): self
    {
        return new self(
            name: $model->name,
            public: $model->public,
            retention: Retention::from($model->retention)->toArray(),
            cache: [
                'maxAge' => $model->cache_max_age,
                'visibility' => $model->cache_visibility === null
                    ? null
                    : CacheVisibility::from($model->cache_visibility)->toArray(),
            ],
            createdAt: $model->created_at,
            updatedAt: $model->updated_at,
        );
    }
}
