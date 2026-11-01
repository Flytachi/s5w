<?php

declare(strict_types=1);

namespace Main\Sweepers\Orphans;

use Flytachi\Winter\Cdo\Qb;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Main\Repositories\BlobRepository;
use Main\Repositories\BucketRepository;
use Main\Storage\BlobStore;
use Main\Sweepers\Sweeper;
use Psr\Log\LoggerInterface;

#[Singleton]
final class OrphanBlobFilesSweeper implements Sweeper
{
    private const int BATCH = 500;
    private const int DISK_AGE = 86400;

    #[Autowired]
    private BlobRepository $repo;

    #[Autowired]
    private BucketRepository $buckets;

    #[Autowired]
    private BlobStore $store;

    #[Autowired]
    private LoggerInterface $log;

    public function sweep(bool $force = false): int
    {
        $removed = 0;
        $edge = time() - self::DISK_AGE;

        foreach ($this->buckets->select('id')->findAll() as $bucket) {
            $path = $this->store->bucketPath($bucket->id);
            if (!is_dir($path)) {
                continue;
            }

            // Пустая таблица при непустой директории — признак сбоя, а не мусора:
            // без этой проверки проход стёр бы бакет целиком.
            if (!$this->repo->where(Qb::eq('bucket_id', $bucket->id))->exists()) {
                $files = $this->countFiles($path);
                if ($files > 0) {
                    $this->log->warning(sprintf(
                        'skipped bucket %s: %d file(s) on disk but no blobs in the database',
                        $bucket->id,
                        $files,
                    ));
                }
                continue;
            }

            $chunk = [];

            foreach (new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS) as $entry) {
                /** @var \SplFileInfo $entry */
                if (!$entry->isFile() || $entry->getMTime() > $edge) {
                    continue;
                }

                $chunk[$entry->getFilename()] = $entry->getPathname();

                if (count($chunk) >= self::BATCH) {
                    $removed += $this->dropStray($bucket->id, $chunk);
                    $chunk = [];
                }
            }

            if ($chunk !== []) {
                $removed += $this->dropStray($bucket->id, $chunk);
            }
        }

        return $removed;
    }

    /**
     * @param array<string, string> $chunk hash => pathname
     */
    private function dropStray(string $bucketId, array $chunk): int
    {
        $known = array_flip(array_column(
            $this->repo->select('hash')->findAllBy(Qb::and(
                Qb::eq('bucket_id', $bucketId),
                Qb::in('hash', array_keys($chunk)),
            )),
            'hash',
        ));

        $removed = 0;

        foreach ($chunk as $hash => $pathname) {
            if (!isset($known[$hash]) && @unlink($pathname)) {
                $removed++;
            }
        }

        return $removed;
    }

    private function countFiles(string $path): int
    {
        $files = 0;

        foreach (new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS) as $entry) {
            /** @var \SplFileInfo $entry */
            if ($entry->isFile()) {
                $files++;
            }
        }

        return $files;
    }
}
