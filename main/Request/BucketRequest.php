<?php

declare(strict_types=1);

namespace Main\Request;

use Flytachi\Winter\Kernel\Http\Request\Validation\Max;
use Flytachi\Winter\Kernel\Http\Request\Validation\NotBlank;
use Flytachi\Winter\Kernel\Http\Request\Validation\Positive;
use Flytachi\Winter\Kernel\Http\Request\Validation\Required;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;

final class BucketRequest
{
    public function __construct(
        #[Required]
        #[NotBlank]
        #[Size(min: 1, max: 100)]
        public string $name,

        #[Size(min: 0, max: 255)]
        public string $description = '',

        #[Positive]
        #[Max(1099511627776)]
        public int $quotaBytes = 104857600,
    ) {
    }
}
