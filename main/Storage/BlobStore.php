<?php

declare(strict_types=1);

namespace Main\Storage;

use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Kernel;

#[Singleton]
final class BlobStore
{
    private const string ROOT = 'chest';

    private const string UPLOADS = 'uploads';

    public function rootPath(): string
    {
        return Kernel::$pathStorage . '/' . self::ROOT;
    }

    public function bucketPath(string $bucketId): string
    {
        return $this->rootPath() . '/' . $bucketId;
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
            throw new \RuntimeException("Failed to create the storage directory of bucket {$bucketId}");
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
            throw new \RuntimeException("Failed to remove the storage directory of bucket {$bucketId}");
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
            throw new \RuntimeException("Failed to stage file content in storage");
        }
        if (!@rename($staging, $target)) {
            @unlink($staging);
            throw new \RuntimeException("Failed to move file content into storage");
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

    /**
     * Недокачанные файлы лежат вне chest: там их сочли бы мусором и стёрли
     * ближайшим ночным проходом.
     */
    public function uploadRoot(): string
    {
        return Kernel::$pathStorage . '/' . self::UPLOADS;
    }

    public function uploadPath(string $uploadId): string
    {
        return $this->uploadRoot() . '/' . $uploadId;
    }

    public function uploadCreate(string $uploadId): void
    {
        $root = $this->uploadRoot();
        if (!is_dir($root) && !@mkdir($root, 0775, true) && !is_dir($root)) {
            throw new \RuntimeException('Failed to create the upload staging directory');
        }

        if (@file_put_contents($this->uploadPath($uploadId), '') === false) {
            throw new \RuntimeException('Failed to create the staging file of this upload');
        }
    }

    /**
     * Дописывает кусок, предварительно обрезав файл до принятого смещения: так
     * повтор куска не удваивает байты, а запись, не дожившая до обновления базы,
     * не оставляет хвоста.
     *
     * @return int размер файла после записи
     */
    public function uploadAppend(string $uploadId, int $offset, string $bytes): int
    {
        $handle = @fopen($this->uploadPath($uploadId), 'c+b');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open the staging file of this upload');
        }

        try {
            if (!ftruncate($handle, $offset) || fseek($handle, $offset) !== 0) {
                throw new \RuntimeException('Failed to position the staging file of this upload');
            }
            if (fwrite($handle, $bytes) !== strlen($bytes)) {
                throw new \RuntimeException('Failed to write the chunk into storage');
            }
            fflush($handle);

            return (int) ftell($handle);
        } finally {
            fclose($handle);
        }
    }

    public function uploadSize(string $uploadId): int
    {
        $path = $this->uploadPath($uploadId);

        return is_file($path) ? (int) filesize($path) : 0;
    }

    public function uploadDelete(string $uploadId): void
    {
        $path = $this->uploadPath($uploadId);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Staging и хранилище на одном устройстве — тогда финал это переименование.
     * На разных пришлось бы копировать, а на гигабайтах это уже заметно.
     */
    public function uploadRenames(): bool
    {
        $chestPath = $this->rootPath();
        $staging = @stat($this->uploadRoot());
        $chest = @stat(is_dir($chestPath) ? $chestPath : Kernel::$pathStorage);

        return $staging === false || $chest === false || $staging['dev'] === $chest['dev'];
    }
}
