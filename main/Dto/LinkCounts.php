<?php

declare(strict_types=1);

namespace Main\Dto;

final class LinkCounts
{
    public int $total = 0;
    public int $active = 0;
    public int $revoked = 0;
    public int $expired = 0;
}
