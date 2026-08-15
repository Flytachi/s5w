<?php

declare(strict_types=1);

namespace Main\Dto;

use Main\Entity\AccessToken;
use Main\Enum\TokenStatus;

final class AccessTokenRes
{
    /**
     * @param array{id: int, name: string} $status
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $status,
        public bool $expired,
        public ?string $expiresAt,
        public ?string $lastUsedAt,
        public string $createdAt,
    ) {
    }

    public static function from(AccessToken $model): self
    {
        return new self(
            id: $model->id,
            name: $model->name,
            status: TokenStatus::from($model->status)->toArray(),
            expired: $model->expires_at !== null && strtotime($model->expires_at) <= time(),
            expiresAt: $model->expires_at,
            lastUsedAt: $model->last_used_at,
            createdAt: $model->created_at,
        );
    }
}
