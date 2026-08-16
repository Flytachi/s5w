<?php

declare(strict_types=1);

namespace Main\Web;

/**
 * Данные для макета админки.
 *
 * Структуры повторяют ответы API (`BucketRes`, `FolderRes`, `FileRes`,
 * `AccessTokenRes`, `ShareLinkRes`) — когда дойдём до подключения бэкенда,
 * во вьюхах менять будет нечего, только источник.
 */
final class MockData
{
    private const string BUCKET_MEDIA = '1b826036-e5a0-459d-87fd-fb7800cff67b';
    private const string BUCKET_DOCS = '7f31a4c2-1d9e-4b77-9a10-2c6f5b83e401';
    private const string BUCKET_AVATARS = 'c94e7b15-8a02-4de6-b3f1-59d0aa7c2e88';
    private const string BUCKET_BACKUP = '2ad6f109-4c73-4e51-8b92-71e3c0d4f5a6';

    /** @return array<int, array<string, mixed>> */
    public static function buckets(): array
    {
        return [
            [
                'id' => self::BUCKET_MEDIA,
                'name' => 'media-lab',
                'description' => 'фото и видео витрины',
                'bytes' => ['quota' => 536870912, 'used' => 341827584, 'free' => 195043328],
                'status' => ['id' => 3, 'name' => 'ACTIVE'],
                'cache' => ['maxAge' => 86400, 'visibility' => ['id' => 1, 'name' => 'PUBLIC']],
                'files' => 1284,
                'blobs' => 1102,
                'folders' => 3,
                'createdAt' => '2026-05-04 11:20:00',
            ],
            [
                'id' => self::BUCKET_DOCS,
                'name' => 'documents',
                'description' => 'договоры и акты, только по токену',
                'bytes' => ['quota' => 214748364, 'used' => 201326592, 'free' => 13421772],
                'status' => ['id' => 3, 'name' => 'ACTIVE'],
                'cache' => ['maxAge' => 0, 'visibility' => ['id' => 2, 'name' => 'PRIVATE']],
                'files' => 3921,
                'blobs' => 3788,
                'folders' => 5,
                'createdAt' => '2026-03-17 09:02:00',
            ],
            [
                'id' => self::BUCKET_AVATARS,
                'name' => 'avatars',
                'description' => 'аватарки, всё приводится к webp 512',
                'bytes' => ['quota' => 104857600, 'used' => 41943040, 'free' => 62914560],
                'status' => ['id' => 3, 'name' => 'ACTIVE'],
                'cache' => ['maxAge' => 604800, 'visibility' => ['id' => 1, 'name' => 'PUBLIC']],
                'files' => 8734,
                'blobs' => 6210,
                'folders' => 1,
                'createdAt' => '2026-01-29 15:41:00',
            ],
            [
                'id' => self::BUCKET_BACKUP,
                'name' => 'nightly-backup',
                'description' => 'выгрузки, чистятся раз в неделю',
                'bytes' => ['quota' => 1073741824, 'used' => 12582912, 'free' => 1061158912],
                'status' => ['id' => 0, 'name' => 'CREATED'],
                'cache' => ['maxAge' => null, 'visibility' => null],
                'files' => 6,
                'blobs' => 6,
                'folders' => 1,
                'createdAt' => '2026-08-16 08:15:00',
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function bucket(string $id): ?array
    {
        foreach (self::buckets() as $bucket) {
            if ($bucket['id'] === $id) {
                return $bucket;
            }
        }

        return null;
    }

    public static function defaultBucketId(): string
    {
        return self::BUCKET_MEDIA;
    }

    /** @return array<int, array<string, mixed>> */
    public static function folders(string $bucketId): array
    {
        $all = [
            self::BUCKET_MEDIA => [
                ['name' => 'photos', 'public' => true, 'retention' => ['id' => 0, 'name' => 'NONE'],
                 'cache' => ['maxAge' => 86400, 'visibility' => 'PUBLIC'], 'files' => 812, 'bytes' => 214958080],
                ['name' => 'video', 'public' => true, 'retention' => ['id' => 0, 'name' => 'NONE'],
                 'cache' => ['maxAge' => 604800, 'visibility' => 'PUBLIC'], 'files' => 47, 'bytes' => 118489088],
                ['name' => 'drafts', 'public' => false, 'retention' => ['id' => 2, 'name' => 'WEEK'],
                 'cache' => ['maxAge' => 0, 'visibility' => 'NO_STORE'], 'files' => 425, 'bytes' => 8380416],
            ],
            self::BUCKET_DOCS => [
                ['name' => 'contracts', 'public' => false, 'retention' => ['id' => 6, 'name' => 'YEAR'],
                 'cache' => ['maxAge' => 0, 'visibility' => 'PRIVATE'], 'files' => 1902, 'bytes' => 96468992],
                ['name' => 'acts', 'public' => false, 'retention' => ['id' => 5, 'name' => 'HALF_YEAR'],
                 'cache' => ['maxAge' => 0, 'visibility' => 'PRIVATE'], 'files' => 1544, 'bytes' => 71303168],
                ['name' => 'inbox', 'public' => false, 'retention' => ['id' => 3, 'name' => 'MONTH'],
                 'cache' => ['maxAge' => 0, 'visibility' => 'PRIVATE'], 'files' => 401, 'bytes' => 27262976],
                ['name' => 'exports', 'public' => false, 'retention' => ['id' => 1, 'name' => 'DAY'],
                 'cache' => ['maxAge' => 0, 'visibility' => 'NO_STORE'], 'files' => 62, 'bytes' => 5242880],
                ['name' => 'archive', 'public' => false, 'retention' => ['id' => 0, 'name' => 'NONE'],
                 'cache' => ['maxAge' => 0, 'visibility' => 'PRIVATE'], 'files' => 12, 'bytes' => 1048576],
            ],
        ];

        return $all[$bucketId] ?? [
            ['name' => 'default', 'public' => true, 'retention' => ['id' => 0, 'name' => 'NONE'],
             'cache' => ['maxAge' => null, 'visibility' => null], 'files' => 6, 'bytes' => 12582912],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function files(string $bucketId): array
    {
        $files = [
            [
                'id' => 'k3n8Qv2pLxR9wZ4t', 'name' => 'beach.webp', 'folder' => 'photos', 'public' => true,
                'content' => ['size' => 50585, 'mime' => 'image/webp', 'extension' => 'webp', 'hash' => '9f2c41ab8e77d0'],
                'processed' => ['applied' => true, 'operations' => ['resize:1200x800', 'encode:webp@82'],
                                'source' => ['width' => 3000, 'height' => 2000, 'size' => 908782, 'mime' => 'image/jpeg'],
                                'result' => ['width' => 1200, 'height' => 800, 'size' => 50585, 'mime' => 'image/webp']],
                'deduplicated' => false, 'expiresAt' => null, 'createdAt' => '2026-08-16 10:12:00',
            ],
            [
                'id' => 'pjukwVqq3Nn65s73', 'name' => 'promo.mp4', 'folder' => 'video', 'public' => true,
                'content' => ['size' => 95717000, 'mime' => 'video/mp4', 'extension' => 'mp4', 'hash' => '565e0625f183fb'],
                'processed' => ['applied' => false, 'reason' => 'no options'],
                'deduplicated' => false, 'expiresAt' => null, 'createdAt' => '2026-08-16 09:48:00',
            ],
            [
                'id' => 'voeSIBsqi9pEC6Wc', 'name' => 'portrait.webp', 'folder' => 'photos', 'public' => true,
                'content' => ['size' => 32744, 'mime' => 'image/webp', 'extension' => 'webp', 'hash' => 'c1d0f74a2b9e51'],
                'processed' => ['applied' => true, 'operations' => ['exif-rotate', 'resize:600x400', 'encode:webp@82'],
                                'source' => ['width' => 2000, 'height' => 3000, 'size' => 1719850, 'mime' => 'image/jpeg'],
                                'result' => ['width' => 600, 'height' => 400, 'size' => 32744, 'mime' => 'image/webp']],
                'deduplicated' => false, 'expiresAt' => null, 'createdAt' => '2026-08-15 18:30:00',
            ],
            [
                'id' => 'gGFBUARN4aAcy6qz', 'name' => 'договор №148.pdf', 'folder' => 'drafts', 'public' => false,
                'content' => ['size' => 35223, 'mime' => 'application/pdf', 'extension' => 'pdf', 'hash' => '4ab21ce7790d38'],
                'processed' => ['applied' => false, 'reason' => 'no options'],
                'deduplicated' => true, 'expiresAt' => '2026-08-23 18:04:00', 'createdAt' => '2026-08-16 18:04:00',
            ],
            [
                'id' => 'buipgtSN6wS3mQm1', 'name' => 'loader.gif', 'folder' => 'photos', 'public' => true,
                'content' => ['size' => 321, 'mime' => 'image/gif', 'extension' => 'gif', 'hash' => '77aa10bd4c9e02'],
                'processed' => ['applied' => false, 'reason' => 'animated'],
                'deduplicated' => false, 'expiresAt' => null, 'createdAt' => '2026-08-15 12:02:00',
            ],
            [
                'id' => 'qGAgPelDasUbrU31', 'name' => 'screenshot.png', 'folder' => 'drafts', 'public' => false,
                'content' => ['size' => 74765, 'mime' => 'image/png', 'extension' => 'png', 'hash' => 'e0917bb5d3ca46'],
                'processed' => ['applied' => false, 'reason' => 'output is larger than source'],
                'deduplicated' => false, 'expiresAt' => '2026-08-22 09:31:00', 'createdAt' => '2026-08-15 09:31:00',
            ],
            [
                'id' => 'Rr7cZmT1xKq04PdA', 'name' => 'track.mp3', 'folder' => 'video', 'public' => true,
                'content' => ['size' => 129192, 'mime' => 'audio/mpeg', 'extension' => 'mp3', 'hash' => '3bb8091ee4dd7f'],
                'processed' => ['applied' => false, 'reason' => 'no options'],
                'deduplicated' => true, 'expiresAt' => null, 'createdAt' => '2026-08-14 22:14:00',
            ],
            [
                'id' => 'Wm2vYh8sLp5nBc0e', 'name' => 'backup.tar.gz', 'folder' => null, 'public' => true,
                'content' => ['size' => 4194304, 'mime' => 'application/gzip', 'extension' => 'gz', 'hash' => 'ff30ac81b7e259'],
                'processed' => ['applied' => false, 'reason' => 'no options'],
                'deduplicated' => false, 'expiresAt' => null, 'createdAt' => '2026-08-14 03:00:00',
            ],
        ];

        // у пустого бакета — пустой список, чтобы было видно и это состояние
        return $bucketId === self::BUCKET_BACKUP ? [] : $files;
    }

    /** @return array<int, array<string, mixed>> */
    public static function tokens(string $bucketId): array
    {
        if ($bucketId === self::BUCKET_BACKUP) {
            return [];
        }

        return [
            ['id' => 12, 'name' => 'мобильное приложение', 'status' => ['id' => 1, 'name' => 'ACTIVE'],
             'expired' => false, 'expiresAt' => '2026-11-14 12:00:00', 'lastUsedAt' => '2026-08-16 10:41:00',
             'createdAt' => '2026-08-16 12:00:00'],
            ['id' => 9, 'name' => 'сайт-витрина', 'status' => ['id' => 1, 'name' => 'ACTIVE'],
             'expired' => false, 'expiresAt' => null, 'lastUsedAt' => '2026-08-16 11:02:00',
             'createdAt' => '2026-06-02 14:20:00'],
            ['id' => 7, 'name' => 'подрядчик (импорт)', 'status' => ['id' => 0, 'name' => 'INACTIVE'],
             'expired' => false, 'expiresAt' => '2026-09-01 00:00:00', 'lastUsedAt' => '2026-07-29 16:33:00',
             'createdAt' => '2026-05-11 10:05:00'],
            ['id' => 4, 'name' => 'старый CI', 'status' => ['id' => 1, 'name' => 'ACTIVE'],
             'expired' => true, 'expiresAt' => '2026-08-01 00:00:00', 'lastUsedAt' => '2026-07-31 23:58:00',
             'createdAt' => '2026-02-14 08:00:00'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function links(string $bucketId): array
    {
        if ($bucketId === self::BUCKET_BACKUP) {
            return [];
        }

        return [
            ['id' => 41, 'file' => 'договор №148.pdf', 'slug' => 'gGFBUARN4aAcy6qz',
             'url' => 'http://localhost:9090/t/AQAAAAAAAABDaoGcugMAAAABAAAAAAAAAAKG8FAjptmpwAuHmHHmDDQW',
             'expiresAt' => '2026-08-16 19:40:00', 'revocable' => true, 'maxDownloads' => 5, 'downloads' => 2,
             'revoked' => false, 'disposition' => ['id' => 1, 'name' => 'ATTACHMENT'], 'note' => 'для подрядчика'],
            ['id' => 38, 'file' => 'promo.mp4', 'slug' => 'pjukwVqq3Nn65s73',
             'url' => 'http://localhost:9090/t/AQAAAAAAAAAYaoGcmgMAAAABAAAAAAAAAAGxKSscN0vevH0b6BiqAvOL',
             'expiresAt' => '2026-08-17 09:00:00', 'revocable' => true, 'maxDownloads' => null, 'downloads' => 17,
             'revoked' => false, 'disposition' => ['id' => 0, 'name' => 'INLINE'], 'note' => 'превью для отдела'],
            ['id' => 33, 'file' => 'screenshot.png', 'slug' => 'qGAgPelDasUbrU31',
             'url' => 'http://localhost:9090/t/AQAAAAAAAAB3aoGRHwMAAAABAAAAAAAAAA8m3NMaO7r0F3RN6iZxIErN',
             'expiresAt' => '2026-08-16 12:10:00', 'revocable' => true, 'maxDownloads' => 1, 'downloads' => 1,
             'revoked' => true, 'disposition' => ['id' => 1, 'name' => 'ATTACHMENT'], 'note' => 'разовая выгрузка'],
        ];
    }

    /** Сводка для обзора. */
    public static function overview(): array
    {
        $buckets = self::buckets();

        return [
            'buckets' => count($buckets),
            'files' => array_sum(array_column($buckets, 'files')),
            'blobs' => array_sum(array_column($buckets, 'blobs')),
            'used' => array_sum(array_column(array_column($buckets, 'bytes'), 'used')),
            'quota' => array_sum(array_column(array_column($buckets, 'bytes'), 'quota')),
            'saved' => 2483027968,
            'tokens' => 11,
            'links' => 7,
            // загрузки по дням — рисуем спарклайном прямо в разметке
            'uploads' => [128, 96, 210, 184, 240, 173, 268, 312, 254, 298, 341, 386, 352, 410],
            'traffic' => [
                ['label' => 'пн', 'value' => 62], ['label' => 'вт', 'value' => 78], ['label' => 'ср', 'value' => 54],
                ['label' => 'чт', 'value' => 91], ['label' => 'пт', 'value' => 100], ['label' => 'сб', 'value' => 38],
                ['label' => 'вс', 'value' => 27],
            ],
            'types' => [
                ['kind' => 'image', 'label' => 'картинки', 'share' => 46, 'bytes' => 274877906],
                ['kind' => 'video', 'label' => 'видео', 'share' => 31, 'bytes' => 185273548],
                ['kind' => 'doc', 'label' => 'документы', 'share' => 14, 'bytes' => 83676037],
                ['kind' => 'audio', 'label' => 'аудио', 'share' => 6, 'bytes' => 35862118],
                ['kind' => 'other', 'label' => 'прочее', 'share' => 3, 'bytes' => 17931059],
            ],
            'channels' => [
                ['channel' => 'o', 'label' => 'открытая отдача', 'hits' => 184203, 'share' => 71],
                ['channel' => 'p', 'label' => 'по токену', 'hits' => 62841, 'share' => 24],
                ['channel' => 't', 'label' => 'временные ссылки', 'hits' => 12604, 'share' => 5],
            ],
        ];
    }

    /** Последние события — для ленты на обзоре. */
    public static function events(): array
    {
        return [
            ['icon' => 'i-upload', 'tone' => 'ok', 'text' => '<b>beach.webp</b> загружен в media-lab / photos',
             'meta' => 'сжат из 887 КБ до 49 КБ', 'at' => '2026-08-16 10:12:00'],
            ['icon' => 'i-link', 'tone' => 'temp', 'text' => 'выпущена временная ссылка на <b>договор №148.pdf</b>',
             'meta' => 'лимит 5 скачиваний, час жизни', 'at' => '2026-08-16 09:55:00'],
            ['icon' => 'i-key', 'tone' => 'brand', 'text' => 'ротация токена <b>мобильное приложение</b>',
             'meta' => 'старый ключ погашен', 'at' => '2026-08-16 08:41:00'],
            ['icon' => 'i-alert-triangle', 'tone' => 'warn', 'text' => 'бакет <b>documents</b> занял 94% квоты',
             'meta' => 'свободно 12,8 МБ', 'at' => '2026-08-16 07:20:00'],
            ['icon' => 'i-trash', 'tone' => 'danger', 'text' => 'уборщик удалил 128 файлов в <b>media-lab / drafts</b>',
             'meta' => 'истёк недельный срок хранения', 'at' => '2026-08-16 03:00:00'],
            ['icon' => 'i-bucket', 'tone' => 'mute', 'text' => 'создан бакет <b>nightly-backup</b>',
             'meta' => 'каталог заведён фоном', 'at' => '2026-08-16 08:15:00'],
        ];
    }
}
