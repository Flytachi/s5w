<?php

declare(strict_types=1);

namespace Main\Web;

use Main\Dto\FileRes;

final readonly class FileView
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $folder,
        public int $size,
        public string $mime,
        public string $extension,
        public string $hash,
        public bool $public,
        public bool $deduplicated,
        public ?string $expiresAt,
        public string $createdAt,
        public string $privateUrl,
        public ?string $publicUrl,
        public ?array $processed,
    ) {
    }

    public static function from(FileRes $file): self
    {
        return new self(
            slug: $file->id,
            name: $file->name,
            folder: $file->folder,
            size: $file->content['size'],
            mime: $file->content['mime'],
            extension: $file->content['extension'],
            hash: $file->content['hash'],
            public: $file->public,
            deduplicated: $file->deduplicated,
            expiresAt: $file->expiresAt,
            createdAt: $file->createdAt,
            privateUrl: $file->privateUrl,
            publicUrl: $file->publicUrl,
            processed: $file->processed,
        );
    }

    public function kind(): array
    {
        return Fmt::kind($this->mime);
    }

    public function channel(): string
    {
        return $this->public ? 'o' : 'p';
    }

    public function processedLabel(): ?string
    {
        if ($this->processed === null) {
            return null;
        }

        return ($this->processed['applied'] ?? false)
            ? implode(', ', $this->processed['operations'])
            : ($this->processed['reason'] ?? null);
    }
}
