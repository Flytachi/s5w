<?php

declare(strict_types=1);

namespace Main\Support;

final class LinkPayload
{
    public function __construct(
        public int $fileId,
        public int $expiresAt,
        public bool $attachment,
        public int $epoch,
        public ?int $jti = null,
    ) {
    }
}
