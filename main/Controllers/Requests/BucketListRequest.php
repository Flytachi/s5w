<?php

declare(strict_types=1);

namespace Main\Controllers\Requests;

use Flytachi\Winter\Kernel\Http\Request\Validation\In;
use Flytachi\Winter\Kernel\Http\Request\Validation\Max;
use Flytachi\Winter\Kernel\Http\Request\Validation\Min;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;

final class BucketListRequest
{
    public const array SORTS = ['name', 'used', 'quota', 'created'];

    public const int PER_PAGE = 15;

    public function __construct(
        #[Min(1)]
        public int $page = 1,

        #[Min(5)]
        #[Max(100)]
        public int $limit = self::PER_PAGE,

        #[Size(min: 0, max: 100)]
        public ?string $search = null,

        #[In(self::SORTS)]
        public string $sort = 'created',

        #[In(['asc', 'desc'])]
        public string $dir = 'desc',
    ) {
    }

    public function orderBy(): string
    {
        $column = match ($this->sort) {
            'name' => 'name',
            'used' => 'used_bytes',
            'quota' => 'quota_bytes',
            default => 'created_at',
        };

        return $column . ' ' . ($this->dir === 'asc' ? 'ASC' : 'DESC');
    }
}
