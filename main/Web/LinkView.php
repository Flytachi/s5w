<?php

declare(strict_types=1);

namespace Main\Web;

use Main\Entity\FileEntry;
use Main\Entity\ShareLink;
use Main\Enum\Disposition;

final readonly class LinkView
{
    public function __construct(
        public int $id,
        public string $fileName,
        public string $fileSlug,
        public string $expiresAt,
        public bool $revoked,
        public bool $expired,
        public ?int $maxDownloads,
        public int $downloads,
        public bool $attachment,
        public string $note,
        public string $createdAt,
        public string $url = '',
    ) {
    }

    public static function from(ShareLink $link, ?FileEntry $file, string $url = ''): self
    {
        return new self(
            id: $link->id,
            fileName: $file?->name ?? 'файл удалён',
            fileSlug: $file?->slug ?? '',
            expiresAt: $link->expires_at,
            revoked: $link->revoked,
            expired: strtotime($link->expires_at) <= time(),
            maxDownloads: $link->max_downloads,
            downloads: $link->downloads,
            attachment: Disposition::from($link->disposition)->isAttachment(),
            note: $link->note,
            createdAt: $link->created_at,
            url: $url,
        );
    }

    public function isAlive(): bool
    {
        return !$this->revoked
            && !$this->expired
            && ($this->maxDownloads === null || $this->downloads < $this->maxDownloads);
    }

    public function deadReason(): ?string
    {
        return match (true) {
            $this->revoked => 'отозвана',
            $this->expired => 'срок истёк',
            $this->maxDownloads !== null && $this->downloads >= $this->maxDownloads => 'лимит исчерпан',
            default => null,
        };
    }
}
