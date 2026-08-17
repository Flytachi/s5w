<?php

declare(strict_types=1);

namespace Main\Http;

use Flytachi\Winter\DI\Attribute\Request;
use Main\Entity\AccessToken;

#[Request]
final class BucketContext
{
    private ?string $bucketId = null;

    private ?AccessToken $token = null;

    public function set(string $bucketId): void
    {
        $this->bucketId = $bucketId;
    }

    public function setToken(AccessToken $token): void
    {
        $this->token = $token;
        $this->bucketId = $token->bucket_id;
    }

    public function token(): AccessToken
    {
        if ($this->token === null) {
            throw new \LogicException('BucketContext is empty — middleware did not run');
        }

        return $this->token;
    }

    public function bucketId(): string
    {
        if ($this->bucketId === null) {
            throw new \LogicException('BucketContext is empty — middleware did not run');
        }

        return $this->bucketId;
    }
}
