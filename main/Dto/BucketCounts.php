<?php

declare(strict_types=1);

namespace Main\Dto;

final class BucketCounts
{
    public int $total = 0;
    public int $active = 0;
    public int $pending = 0;
    public int $full = 0;
    public int $used = 0;
    public int $quota = 0;
}
