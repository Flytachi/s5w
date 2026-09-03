<?php

declare(strict_types=1);

namespace Main\Dto;

final class TokenCounts
{
    public int $total = 0;
    public int $active = 0;
    public int $full = 0;
    public int $inactive = 0;
    public int $expired = 0;
}
