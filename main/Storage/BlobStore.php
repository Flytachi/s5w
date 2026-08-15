<?php

declare(strict_types=1);

namespace Main\Storage;

use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Kernel;

/**
 * Файловое хранилище: storage/chest/{bucketId}/{sha256}.
 *
 * Пока здесь только каталог бакета — работа с блобами приедет вместе с файлами.
 */
#[Singleton]
final class BlobStore
{
    private const string ROOT = 'chest';

    public function bucketPath(string $bucketId): string
    {
        return Kernel::$pathStorage . '/' . self::ROOT . '/' . $bucketId;
    }

    public function bucketExists(string $bucketId): bool
    {
        return is_dir($this->bucketPath($bucketId));
    }

    /** Идемпотентно: существующий каталог — не ошибка. */
    public function createBucketDir(string $bucketId): void
    {
        $path = $this->bucketPath($bucketId);
        if (is_dir($path)) {
            return;
        }
        if (!@mkdir($path, 0775, true) && !is_dir($path)) {
            throw new \RuntimeException("Failed to create bucket dir \"{$path}\"");
        }
    }

    /** Идемпотентно: отсутствующий каталог — не ошибка. */
    public function removeBucketDir(string $bucketId): void
    {
        $path = $this->bucketPath($bucketId);
        if (!is_dir($path)) {
            return;
        }

        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($entries as $entry) {
            /** @var \SplFileInfo $entry */
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }

        if (!@rmdir($path) && is_dir($path)) {
            throw new \RuntimeException("Failed to remove bucket dir \"{$path}\"");
        }
    }
}
