<?php

declare(strict_types=1);

namespace Main\Dto;

use Main\Entities\Bucket;

final class BucketCardRes
{
    public function __construct(
        public BucketRes $bucket,
        public int $files,
        public int $blobs,
        public int $folders,
    ) {
    }

    /**
     * @param array{files: int, blobs: int, folders: int} $stats
     */
    public static function from(Bucket $bucket, array $stats): self
    {
        return new self(BucketRes::from($bucket), $stats['files'], $stats['blobs'], $stats['folders']);
    }

    public function percent(): float
    {
        $quota = $this->bucket->bytes['quota'];

        return $quota > 0 ? min(100, $this->bucket->bytes['used'] / $quota * 100) : 0.0;
    }
}
