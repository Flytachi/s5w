<?php

declare(strict_types=1);

namespace Main\Request;

use Flytachi\Winter\Kernel\Http\Request\Validation\Size;


final class FileUploadRequest
{
    public function __construct(
        #[Size(min: 0, max: 100)]
        public ?string $folder = null,

        #[Size(min: 0, max: 255)]
        public ?string $name = null,
    ) {
    }
}
