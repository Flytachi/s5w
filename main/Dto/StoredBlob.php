<?php

declare(strict_types=1);

namespace Main\Dto;

use Main\Entity\Blob;

final class StoredBlob
{
    public function __construct(
        public Blob $blob,
        public bool $deduplicated,
        public string $mimeType,
        public string $extension,
    ) {
    }
}
