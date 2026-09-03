<?php

declare(strict_types=1);

namespace Main\Request;

use Flytachi\Winter\Kernel\Http\Request\Validation\NotBlank;
use Flytachi\Winter\Kernel\Http\Request\Validation\Regex;
use Flytachi\Winter\Kernel\Http\Request\Validation\Required;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;
use Main\Enum\Retention;

final class FolderRequest
{
    public function __construct(
        #[Required]
        #[NotBlank]
        #[Size(min: 1, max: 100)]
        #[Regex('/^[\p{L}\p{N}][\p{L}\p{N} ._-]*$/u', 'may contain letters, digits, space, dot, underscore, dash')]
        public string $name,

        public bool $public = false,

        public Retention $retention = Retention::NONE,
    ) {
    }
}
