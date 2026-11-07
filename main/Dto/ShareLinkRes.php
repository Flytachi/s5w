<?php

declare(strict_types=1);

namespace Main\Dto;

use Main\Entities\ShareLink;
use Main\Enum\Disposition;
use Main\Support\Time;

final class ShareLinkRes
{
    /**
     * @param array{id: int, name: string} $disposition
     */
    public function __construct(
        public ?int $id,
        public string $url,
        public string $expiresAt,
        public bool $revocable,
        public ?int $maxDownloads,
        public int $downloads,
        public bool $revoked,
        public array $disposition,
        public string $note,
    ) {
    }

    public static function from(ShareLink $link, string $url): self
    {
        return new self(
            id: $link->id,
            url: $url,
            expiresAt: (string) Time::iso($link->expires_at),
            revocable: true,
            maxDownloads: $link->max_downloads,
            downloads: $link->downloads,
            revoked: $link->revoked,
            disposition: Disposition::from($link->disposition)->toArray(),
            note: $link->note,
        );
    }

    public static function stateless(string $url, string $expiresAt, Disposition $disposition): self
    {
        return new self(
            id: null,
            url: $url,
            expiresAt: (string) Time::iso($expiresAt),
            revocable: false,
            maxDownloads: null,
            downloads: 0,
            revoked: false,
            disposition: $disposition->toArray(),
            note: '',
        );
    }
}
