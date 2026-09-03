<?php

declare(strict_types=1);

namespace Main\Request;

use Flytachi\Winter\Kernel\Http\Request\Validation\In;
use Flytachi\Winter\Kernel\Http\Request\Validation\Max;
use Flytachi\Winter\Kernel\Http\Request\Validation\Min;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;

final class LinkListRequest
{
    public const array SORTS = ['created', 'file', 'mode', 'state'];

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

    public function url(string $bucketId, array $override = []): string
    {
        $params = array_filter(
            [
                'search' => $this->search,
                'sort' => $this->sort === 'created' ? null : $this->sort,
                'dir' => $this->dir === 'desc' ? null : $this->dir,
                'page' => $this->page === 1 ? null : $this->page,
                'limit' => $this->limit === self::PER_PAGE ? null : $this->limit,
            ],
            static fn($value) => $value !== null && $value !== '',
        );

        $params = array_filter($override + $params, static fn($value) => $value !== null && $value !== '');
        $query = http_build_query($params);

        return '/admin/ui/buckets/' . $bucketId . '/links' . ($query === '' ? '' : '?' . $query);
    }

    public function sortUrl(string $bucketId, string $sort): string
    {
        return $this->url($bucketId, [
            'sort' => $sort,
            'dir' => $this->sort === $sort && $this->dir === 'desc' ? 'asc' : 'desc',
            'page' => 1,
        ]);
    }

    public function sortArrow(string $sort): ?string
    {
        return $this->sort === $sort ? ($this->dir === 'asc' ? '↑' : '↓') : null;
    }
}
