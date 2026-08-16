<?php

declare(strict_types=1);

namespace Main\Dto;

/** Строка сгруппированного подсчёта: во что гидрируется `count(*) … GROUP BY bucket_id`. */
final class GroupCount
{
    public string $bucket_id;
    public int $total;
}
