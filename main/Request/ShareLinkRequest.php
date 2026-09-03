<?php

declare(strict_types=1);

namespace Main\Request;

use Flytachi\Winter\Kernel\Http\Request\Validation\Max;
use Flytachi\Winter\Kernel\Http\Request\Validation\Positive;
use Flytachi\Winter\Kernel\Http\Request\Validation\Required;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;
use Main\Enum\Disposition;

final class ShareLinkRequest
{
    public function __construct(
        #[Required]
        #[Positive]
        #[Max(2592000)]
        public int $ttl = 3600,

        public Disposition $disposition = Disposition::INLINE,

        #[Positive]
        #[Max(1000)]
        public ?int $maxDownloads = null,

        public bool $revocable = false,

        #[Size(min: 0, max: 255)]
        public string $note = '',
    ) {
    }
}
