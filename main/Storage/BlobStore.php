<?php

declare(strict_types=1);

namespace Main\Storage;

use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Kernel;

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

    public function blobPath(string $bucketId, string $hash): string
    {
        return $this->bucketPath($bucketId) . '/' . $hash;
    }

    public function blobExists(string $bucketId, string $hash): bool
    {
        return is_file($this->blobPath($bucketId, $hash));
    }

    public function blobSize(string $bucketId, string $hash): int
    {
        return (int) @filesize($this->blobPath($bucketId, $hash));
    }

    public function blobWrite(string $bucketId, string $hash, string $srcPath): void
    {
        $this->createBucketDir($bucketId);
        $target = $this->blobPath($bucketId, $hash);

        if (@rename($srcPath, $target)) {
            @chmod($target, 0664);
            return;
        }

        $staging = $target . '.' . bin2hex(random_bytes(4)) . '.part';
        if (!@copy($srcPath, $staging)) {
            @unlink($staging);
            throw new \RuntimeException("Failed to copy blob to \"{$staging}\"");
        }
        if (!@rename($staging, $target)) {
            @unlink($staging);
            throw new \RuntimeException("Failed to store blob at \"{$target}\"");
        }
        @chmod($target, 0664);
        @unlink($srcPath);
    }

    public function blobDelete(string $bucketId, string $hash): void
    {
        $path = $this->blobPath($bucketId, $hash);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
