<?php

declare(strict_types=1);

namespace Main\Dto;

final class UploadCounts
{
    public int $total = 0;
    public int $staged = 0;
    public int $expired = 0;
}
