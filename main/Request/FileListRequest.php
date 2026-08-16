<?php

declare(strict_types=1);

namespace Main\Request;

use Flytachi\Winter\Kernel\Http\Request\Validation\In;
use Flytachi\Winter\Kernel\Http\Request\Validation\Max;
use Flytachi\Winter\Kernel\Http\Request\Validation\Min;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;

final class FileListRequest
{
    /** Разрешённые поля: дата, имя, тип содержимого и вес. */
    public const array SORTS = ['created', 'name', 'type', 'size'];

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

        #[In(self::SORTS)]
        public string $sort = 'created',

        #[In(['asc', 'desc'])]
        public string $dir = 'desc',
    ) {
    }
}
