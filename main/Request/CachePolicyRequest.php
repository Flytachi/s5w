<?php

declare(strict_types=1);

namespace Main\Request;

use Flytachi\Winter\Kernel\Http\Request\Validation\Max;
use Flytachi\Winter\Kernel\Http\Request\Validation\PositiveOrZero;
use Main\Enum\CacheVisibility;

final class CachePolicyRequest
{
    public function __construct(
        #[PositiveOrZero]
        #[Max(31536000)]
        public ?int $maxAge = null,

        public ?CacheVisibility $visibility = null,
    ) {
    }
}
