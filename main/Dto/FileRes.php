<?php

declare(strict_types=1);

namespace Main\Dto;

use Main\Entity\Blob;
use Main\Entity\FileEntry;
use Main\Image\ProcessedImage;

final class FileRes
{
    /**
     * @param array{size: int, mime: string, extension: string, hash: string} $content
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $folder,
        public array $content,
        public bool $public,
        public bool $deduplicated,
        public ?array $processed,
        public ?string $expiresAt,
        public string $privateUrl,
        public ?string $publicUrl,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function from(
        FileEntry $file,
        Blob $blob,
        ?string $folderName,
        string $baseUrl,
        bool $deduplicated = false,
        ?ProcessedImage $processed = null,
    ): self {
        $base = rtrim($baseUrl, '/');

        return new self(
            id: $file->slug,
            name: $file->name,
            folder: $folderName,
            content: [
                // размер и хэш — от блоба, тип — от файла (docs/plan.md §2.4)
                'size' => $blob->size_bytes,
                'mime' => $file->mime_type,
                'extension' => $file->extension,
                'hash' => $blob->hash,
            ],
            public: $file->public,
            deduplicated: $deduplicated,
            processed: $processed?->toArray(),
            expiresAt: $file->expires_at,
            privateUrl: $base . '/p/' . $file->slug,
            publicUrl: $file->public ? $base . '/o/' . $file->bucket_id . '/' . $file->slug : null,
            createdAt: $file->created_at,
            updatedAt: $file->updated_at,
        );
    }
}
