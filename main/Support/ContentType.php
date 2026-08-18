<?php

declare(strict_types=1);

namespace Main\Support;

final class ContentType
{
    public const string FALLBACK = 'application/octet-stream';

    private const int EXTENSION_LIMIT = 16;

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
        $hint = self::hintOf((string) $sourceName);

        if ($mime === 'text/plain' && $hint !== '') {
            $extension = $hint;
            $mime = self::mimeFor($hint) ?: $mime;
        } elseif ($extension === '') {
            $extension = $hint;
        }

        return ['mime' => $mime, 'extension' => $extension];
    }

    private static function hintOf(string $name): string
    {
        $extension = strtolower(self::extensionOf($name));

        return preg_match('/^[a-z0-9]{1,' . self::EXTENSION_LIMIT . '}$/', $extension) === 1
            ? $extension
            : '';
    }

    private static function sniff(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return self::FALLBACK;
        }
        return finfo_file($finfo, $path) ?: self::FALLBACK;
    }

    public static function extensionOf(string $name): string
    {
        $dot = strrpos($name, '.');
        if ($dot === false || $dot === strlen($name) - 1) {
            return '';
        }
        return substr($name, $dot + 1);
    }

    public static function sameExtension(string $a, string $b): bool
    {
        return self::canonical($a) === self::canonical($b);
    }

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
            'log', 'ini', 'conf', 'env' => 'text/plain',
            default => '',
        };
    }
}
