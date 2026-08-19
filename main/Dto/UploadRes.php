<?php

declare(strict_types=1);

namespace Main\Dto;

use Main\Entity\Upload;

final class UploadRes
{
    public function __construct(
        public string $id,
        public string $name,
        public int $size,
        public int $offset,
        public int $chunkSize,
        public string $expiresAt,
        public ?FileRes $file = null,
    ) {
    }

    public static function from(Upload $upload, int $chunkSize): self
    {
        return new self(
            id: (string) $upload->id,
            name: $upload->name,
            size: $upload->size_bytes,
            offset: $upload->offset_bytes,
            chunkSize: $chunkSize,
            expiresAt: $upload->expires_at,
        );
    }

    /** Содержимое нашлось по хешу — загружать нечего, файл уже готов. */
    public static function deduplicated(FileRes $file): self
    {
        $size = (int) ($file->content['size'] ?? 0);

        return new self(
            id: '',
            name: $file->name,
            size: $size,
            offset: $size,
            chunkSize: 0,
            expiresAt: '',
            file: $file,
        );
    }
}
