<?php

declare(strict_types=1);

namespace Main\Controllers\Requests;

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
}
