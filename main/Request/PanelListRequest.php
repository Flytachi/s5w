<?php

declare(strict_types=1);

namespace Main\Request;

use Flytachi\Winter\Kernel\Http\Request\Validation\In;
use Flytachi\Winter\Kernel\Http\Request\Validation\Max;
use Flytachi\Winter\Kernel\Http\Request\Validation\Min;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;

final class PanelListRequest
{
    public const int PER_PAGE = 20;

    public function __construct(
        #[Min(1)]
        public int $page = 1,

        #[Min(5)]
        #[Max(100)]
        public int $limit = self::PER_PAGE,

        #[Size(min: 0, max: 255)]
        public ?string $search = null,

        #[Size(min: 0, max: 100)]
        public ?string $folder = null,

        #[In(FileListRequest::SORTS)]
        public string $sort = 'created',

        #[In(['asc', 'desc'])]
        public string $dir = 'desc',
    ) {
    }

    public function folderFilter(): ?string
    {
        return $this->folder === '/' ? '' : $this->folder;
    }

    public function toFileList(): FileListRequest
    {
        return new FileListRequest(
            page: $this->page,
            limit: $this->limit,
            search: $this->search,
            folder: $this->folderFilter(),
            sort: $this->sort,
            dir: $this->dir,
        );
    }

    public function sortLabel(): string
    {
        return match ($this->sort) {
            'name' => 'по имени',
            'type' => 'по типу',
            'size' => 'по весу',
            default => 'по дате',
        };
    }

    public function isDesc(): bool
    {
        return $this->dir !== 'asc';
    }

    public function dirToggleLabel(): string
    {
        return match ($this->sort) {
            'name', 'type' => $this->isDesc() ? 'от А до Я' : 'от Я до А',
            'size' => $this->isDesc() ? 'сначала лёгкие' : 'сначала тяжёлые',
            default => $this->isDesc() ? 'сначала старые' : 'сначала новые',
        };
    }

    public function toPage(): PageRequest
    {
        return new PageRequest(page: $this->page, limit: $this->limit, search: $this->search);
    }

    public function params(int $page): array
    {
        return [
            'page' => $page,
            'search' => $this->search,
            'folder' => $this->folder,
            'limit' => $this->limit === self::PER_PAGE ? null : $this->limit,
            'sort' => $this->sort === 'created' ? null : $this->sort,
            'dir' => $this->dir === 'desc' ? null : $this->dir,
        ];
    }
}
