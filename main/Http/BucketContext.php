<?php

declare(strict_types=1);

namespace Main\Http;

use Flytachi\Winter\DI\Attribute\Request;

#[Request]
final class BucketContext
{
    private ?string $bucketId = null;

    public function set(string $bucketId): void
    {
        $this->bucketId = $bucketId;
    }

    public function bucketId(): string
    {
        if ($this->bucketId === null) {
            throw new \LogicException('BucketContext is empty — middleware did not run');
        }

        return $this->bucketId;
    }
}
