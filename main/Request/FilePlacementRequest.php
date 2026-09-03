<?php

declare(strict_types=1);

namespace Main\Request;

use Flytachi\Winter\Kernel\Http\Request\Validation\NotBlank;
use Flytachi\Winter\Kernel\Http\Request\Validation\Required;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;

final class FilePlacementRequest
{
    public function __construct(
        #[Required]
        #[NotBlank]
        #[Size(min: 1, max: 255)]
        public string $name,

        #[Size(min: 0, max: 100)]
        public ?string $folder = null,
    ) {
    }
}
