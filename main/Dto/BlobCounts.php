<?php

declare(strict_types=1);

namespace Main\Dto;

final class BlobCounts
{
    public int $blobs = 0;
    public int $stored = 0;
    public int $copies = 0;
    public int $saved = 0;

    public function ratio(): float
    {
        $would = $this->stored + $this->saved;

        return $would > 0 ? $this->saved / $would * 100 : 0.0;
    }
}
