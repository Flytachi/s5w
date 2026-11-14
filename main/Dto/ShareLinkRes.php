<?php

declare(strict_types=1);

namespace Main\Dto;

use Main\Entities\FileEntry;
use Main\Entities\ShareLink;
use Main\Enum\Disposition;
use Main\Support\Time;

final class ShareLinkRes
{
    /**
     * @param array{id: string, name: string}|null $file   файл ссылки: публичный id (slug) и имя;
     *                                                     в API есть всегда (файл удаляется вместе со ссылками)
     * @param array{id: int, name: string}    $disposition
     */
    public function __construct(
        public ?int $id,
        public string $url,
        public ?array $file,
        public string $expiresAt,
        public bool $revocable,
        public ?int $maxDownloads,
        public int $downloads,
        public bool $revoked,
        public array $disposition,
        public string $note,
    ) {
    }

    public static function from(ShareLink $link, string $url, ?FileEntry $file): self
    {
        return new self(
            id: $link->id,
            url: $url,
            file: $file === null ? null : self::fileOf($file),
            expiresAt: (string) Time::iso($link->expires_at),
            revocable: true,
            maxDownloads: $link->max_downloads,
            downloads: $link->downloads,
            revoked: $link->revoked,
            disposition: Disposition::from($link->disposition)->toArray(),
            note: $link->note,
        );
    }

    public static function stateless(string $url, string $expiresAt, Disposition $disposition, FileEntry $file): self
    {
        return new self(
            id: null,
            url: $url,
            file: self::fileOf($file),
            expiresAt: (string) Time::iso($expiresAt),
            revocable: false,
            maxDownloads: null,
            downloads: 0,
            revoked: false,
            disposition: $disposition->toArray(),
            note: '',
        );
    }

    /**
     * Наружу файл идёт по slug — тому же id, что в `/v1/files/{id}`; внутренний `file_id` не
     * показывается.
     *
     * @return array{id: string, name: string}
     */
    private static function fileOf(FileEntry $file): array
    {
        return ['id' => $file->slug, 'name' => $file->name];
    }
}
