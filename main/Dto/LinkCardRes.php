<?php

declare(strict_types=1);

namespace Main\Dto;

use Main\Entities\FileEntry;
use Main\Entities\ShareLink;
use Main\Support\Time;

final class LinkCardRes
{
    public const string ACTIVE = 'active';
    public const string REVOKED = 'revoked';
    public const string EXPIRED = 'expired';
    public const string EXHAUSTED = 'exhausted';

    /**
     * @param array{slug: string, name: string}|null $file null — файл удалён.
     */
    public function __construct(
        public ShareLinkRes $link,
        public ?array $file,
        public string $state,
        public string $createdAt,
    ) {
    }

    public static function from(ShareLink $link, ?FileEntry $file, string $url): self
    {
        return new self(
            link: ShareLinkRes::from($link, $url, $file),
            file: $file === null ? null : ['slug' => $file->slug, 'name' => $file->name],
            state: self::stateOf($link),
            createdAt: (string) Time::iso($link->created_at),
        );
    }

    private static function stateOf(ShareLink $link): string
    {
        return match (true) {
            $link->revoked => self::REVOKED,
            strtotime($link->expires_at) <= time() => self::EXPIRED,
            $link->max_downloads !== null && $link->downloads >= $link->max_downloads => self::EXHAUSTED,
            default => self::ACTIVE,
        };
    }
}
