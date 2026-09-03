<?php

declare(strict_types=1);

namespace Main\Request;

use Flytachi\Winter\Kernel\Http\Request\Validation\NotBlank;
use Flytachi\Winter\Kernel\Http\Request\Validation\Required;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;

final class LoginRequest
{
    public function __construct(
        #[Required]
        #[NotBlank]
        #[Size(min: 1, max: 100)]
        public string $login = '',

        #[Required]
        #[NotBlank]
        #[Size(min: 1, max: 255)]
        public string $password = '',
    ) {
    }
}
