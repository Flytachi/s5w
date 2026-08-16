<?php

declare(strict_types=1);

namespace Main\Support;

/**
 * Тип содержимого — по байтам, а не по имени.
 *
 * Имя из multipart — это ввод пользователя, оно врёт (photo.png внутри JPEG,
 * script.js внутри HTML). Источник истины — finfo; имя используется только там,
 * где байты честно не различают форматы.
 */
final class ContentType
{
    public const string FALLBACK = 'application/octet-stream';

    /** Написания уже приведены к каноничным (см. canonical()). */
    private const array KNOWN = [
        'jpg', 'png', 'gif', 'webp', 'avif', 'bmp', 'tiff', 'svg', 'ico', 'heic',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'rtf', 'epub',
        'txt', 'csv', 'tsv', 'json', 'xml', 'md', 'html', 'yaml', 'css', 'js', 'mjs',
        'sql', 'log', 'ini', 'conf', 'env',
        'zip', 'gz', 'tar', 'bz2', 'xz', 'rar', '7z', 'iso',
        'mp3', 'ogg', 'wav', 'flac', 'aac', 'm4a', 'opus', 'midi',
        'mp4', 'webm', 'mkv', 'mov', 'avi', 'mpeg', 'wmv',
        'ttf', 'otf', 'woff', 'woff2', 'exe', 'apk', 'dmg', 'deb', 'rpm',
    ];

    /**
     * @return array{mime: string, extension: string}
     */
    public static function detect(string $path, ?string $sourceName = null): array
    {
        $mime = self::sniff($path);
        $extension = self::extensionFor($mime);

        // Текстовые форматы finfo почти всегда видит как text/plain: csv, json,
        // xml, md, yaml по байтам — просто текст. Здесь (и только здесь) имя
        // файла информативнее содержимого, поэтому уточняем по нему — но строго
        // по белому списку: у текстового содержимого с именем photo.png не
        // должно получиться расширение png при mime text/plain.
        if ($mime === 'text/plain') {
            $hint = strtolower(self::extensionOf((string) $sourceName));
            $hinted = self::mimeFor($hint);
            if ($hinted !== '') {
                $extension = $hint;
                $mime = $hinted;
            }
        } elseif ($extension === '') {
            $extension = strtolower(self::extensionOf((string) $sourceName));
        }

        return ['mime' => $mime, 'extension' => $extension];
    }

    private static function sniff(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return self::FALLBACK;
        }
        return finfo_file($finfo, $path) ?: self::FALLBACK;
    }

    /** Расширение из имени файла; '' если его нет. */
    public static function extensionOf(string $name): string
    {
        $dot = strrpos($name, '.');
        if ($dot === false || $dot === strlen($name) - 1) {
            return '';
        }
        return substr($name, $dot + 1);
    }

    /**
     * Одно и то же расширение с точностью до общепринятых написаний: иначе
     * загруженный photo.jpeg получил бы имя photo.jpeg.jpg.
     */
    public static function sameExtension(string $a, string $b): bool
    {
        return self::canonical($a) === self::canonical($b);
    }

    /**
     * Похоже ли на указание типа. Нужно, чтобы отличить противоречащее
     * содержимому расширение (его заменяем) от хвоста имени вроде «отчёт v1.2»
     * (к нему просто дописываем).
     */
    public static function isKnownExtension(string $extension): bool
    {
        return in_array(self::canonical($extension), self::KNOWN, true);
    }

    private static function canonical(string $extension): string
    {
        $extension = strtolower($extension);

        return match ($extension) {
            'jpeg' => 'jpg',
            'tif' => 'tiff',
            'htm' => 'html',
            'yml' => 'yaml',
            'mpg' => 'mpeg',
            'mid' => 'midi',
            'markdown' => 'md',
            default => $extension,
        };
    }

    private static function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/svg+xml' => 'svg',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            'application/gzip' => 'gz',
            'application/x-tar' => 'tar',
            'application/json' => 'json',
            'application/xml', 'text/xml' => 'xml',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'text/html' => 'html',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            default => '',
        };
    }

    private static function mimeFor(string $extension): string
    {
        return match ($extension) {
            'csv' => 'text/csv',
            'tsv' => 'text/tab-separated-values',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'md', 'markdown' => 'text/markdown',
            'html', 'htm' => 'text/html',
            'yaml', 'yml' => 'application/yaml',
            'css' => 'text/css',
            'js', 'mjs' => 'text/javascript',
            'sql' => 'application/sql',
            // Расширение сохраняем, тип остаётся тем, что показали байты.
            'log', 'ini', 'conf', 'env' => 'text/plain',
            default => '',
        };
    }
}
