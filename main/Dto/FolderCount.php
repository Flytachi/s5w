<?php

declare(strict_types=1);

namespace Main\Dto;

/** Строка `count(*) … GROUP BY folder_id`. */
final class FolderCount
{
    public int $folder_id;
    public int $total;
}
