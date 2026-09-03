<?php

declare(strict_types=1);

namespace Main\Request;

use Flytachi\Winter\Kernel\Http\Request\Validation\Max;
use Flytachi\Winter\Kernel\Http\Request\Validation\NotBlank;
use Flytachi\Winter\Kernel\Http\Request\Validation\Positive;
use Flytachi\Winter\Kernel\Http\Request\Validation\Required;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;
use Main\Enum\TokenAccess;

final class AccessTokenRequest
{
    public function __construct(
        #[Required]
        #[NotBlank]
        #[Size(min: 1, max: 100)]
        public string $name,

        public TokenAccess $access = TokenAccess::BASIC,

        #[Positive]
        #[Max(3650)]
        public ?int $expiresInDays = null,
    ) {
    }
}
