<?php

declare(strict_types=1);

namespace Main\Request;

use Flytachi\Winter\Kernel\Http\Request\Validation\Max;
use Flytachi\Winter\Kernel\Http\Request\Validation\Min;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;

final class FileListRequest
{
    public function __construct(
        #[Min(1)]
        public int $page = 1,

        #[Min(1)]
        #[Max(100)]
        public int $limit = 25,

        #[Size(min: 0, max: 255)]
        public ?string $search = null,

        #[Size(min: 0, max: 100)]
        public ?string $folder = null,
    ) {
    }
}
