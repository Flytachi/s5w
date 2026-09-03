<?php

declare(strict_types=1);

namespace Main\Dto;

use Main\Entity\AccessToken;
use Main\Entity\Bucket;
use Main\Enum\TokenAccess;

final class CheckRes
{
    /**
     * @param array{id: string, name: string} $bucket
     * @param array{id: int, name: string} $access
     * @param array{quota: int, used: int, free: int} $bytes
     */
    public function __construct(
        public array $bucket,
        public array $access,
        public ?string $expiresAt,
        public array $bytes,
    ) {
    }

    public static function from(AccessToken $token, Bucket $bucket): self
    {
        return new self(
            bucket: ['id' => $bucket->id, 'name' => $bucket->name],
            access: TokenAccess::from($token->access)->toArray(),
            expiresAt: $token->expires_at,
            bytes: [
                'quota' => $bucket->quota_bytes,
                'used' => $bucket->used_bytes,
                'free' => max(0, $bucket->quota_bytes - $bucket->used_bytes),
            ],
        );
    }
}
