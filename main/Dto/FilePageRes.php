<?php

declare(strict_types=1);

namespace Main\Dto;

use Flytachi\Winter\Ppa\Pagination\WrapMeta;

final class FilePageRes
{
    /**
     * @param FileRes[] $data
     * @param FolderCardRes[] $folders
     * @param int $total every file of the bucket, whatever the filter: the "All files" count
     */
    public function __construct(
        public WrapMeta $meta,
        public array $data,
        public array $folders,
        public int $total,
    ) {
    }
}
