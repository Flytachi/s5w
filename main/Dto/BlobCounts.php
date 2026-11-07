<?php

declare(strict_types=1);

namespace Main\Dto;

final class BlobCounts
{
    public int $blobs = 0;
    public int $stored = 0;
    public int $copies = 0;
    public int $saved = 0;
}
