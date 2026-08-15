<?php

declare(strict_types=1);

namespace Main\Dto;

use Main\Entity\Blob;
use Main\Entity\FileEntry;

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
    ): self {
        $base = rtrim($baseUrl, '/');

        return new self(
            id: $file->slug,
            name: $file->name,
            folder: $folderName,
            content: [
                'size' => $blob->size_bytes,
                'mime' => $blob->mime_type,
                'extension' => $blob->extension,
                'hash' => $blob->hash,
            ],
            public: $file->public,
            deduplicated: $deduplicated,
            expiresAt: $file->expires_at,
            privateUrl: $base . '/p/' . $file->slug,
            publicUrl: $file->public ? $base . '/o/' . $file->bucket_id . '/' . $file->slug : null,
            createdAt: $file->created_at,
            updatedAt: $file->updated_at,
        );
    }
}
