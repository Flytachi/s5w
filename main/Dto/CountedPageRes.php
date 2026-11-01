<?php

declare(strict_types=1);

namespace Main\Dto;

use Flytachi\Winter\Ppa\Pagination\WrapMeta;

/**
 * Страница списка вместе со счётчиками над ним: одна страница панели — один запрос.
 */
final class CountedPageRes
{
    public function __construct(
        public WrapMeta $meta,
        public array $data,
        public object|array $counts,
    ) {
    }
}
