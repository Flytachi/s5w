<?php

declare(strict_types=1);

namespace Main\Sweeper;

use Flytachi\Winter\Cdo\Qb;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Schedule\Scheduled;
use Main\Entity\Blob;
use Main\Repository\BlobRepository;
use Main\Repository\BucketRepository;
use Main\Repository\FileEntryRepository;
use Main\Storage\BlobStore;
use Psr\Log\LoggerInterface;

#[Singleton]
final class OrphanBlobSweeper
{
    private const int BATCH = 500;
    private const int MAX_BATCHES = 20;
    private const int DISK_AGE = 86400;

    #[Autowired]
    private BlobRepository $repo;

    #[Autowired]
    private BucketRepository $buckets;

    #[Autowired]
    private FileEntryRepository $files;

    #[Autowired]
    private BlobStore $store;

    #[Autowired]
    private SweepLock $lock;

    #[Autowired]
    private LoggerInterface $log;

    #[Scheduled(cron: '30 3 * * *')]
    public function run(): void
    {
        $this->lock->guard('blobs', function (): void {
            $removed = $this->sweep();
            if ($removed > 0) {
                $this->log->info("removed {$removed} unreferenced blob(s)");
            }
        });
    }

    #[Scheduled(cron: '45 3 * * 0')]
    public function runDisk(): void
    {
        $this->lock->guard('blobs-disk', function (): void {
            $removed = $this->sweepDisk();
            if ($removed > 0) {
                $this->log->info("removed {$removed} stray file(s) from disk");
            }
        });
    }

    public function sweep(): int
    {
        $removed = 0;

        for ($pass = 0; $pass < self::MAX_BATCHES; $pass++) {
            $orphans = $this->repo
                ->where(Qb::raw(sprintf(
                    "created_at <= now() - interval '1 hour'"
                    . ' AND NOT EXISTS (SELECT 1 FROM %s f WHERE f.blob_id = %s.id)',
                    $this->files->originTable(),
                    $this->repo->originTable(),
                )))
                ->orderBy('id')
                ->limit(self::BATCH)
                ->findAll();

            if ($orphans === []) {
                break;
            }

            foreach ($orphans as $blob) {
                /** @var Blob $blob */
                try {
                    $this->drop($blob);
                    $removed++;
                } catch (\Throwable $e) {
                    $this->log->error("failed to remove unreferenced blob {$blob->id}: {$e->getMessage()}");
                }
            }

            if (count($orphans) < self::BATCH) {
                break;
            }
        }

        return $removed;
    }

    public function sweepDisk(): int
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

    private function drop(Blob $blob): void
    {
        $db = $this->repo->db();
        $db->beginTransaction();

        try {
            $this->repo->delete(Qb::eq('id', $blob->id));

            $bucket = $this->buckets->where(Qb::eq('id', $blob->bucket_id))->forBy('UPDATE')->find();
            if ($bucket !== null) {
                $this->buckets->update(
                    [
                        'used_bytes' => max(0, $bucket->used_bytes - $blob->size_bytes),
                        'updated_at' => date('Y-m-d H:i:s P'),
                    ],
                    Qb::eq('id', $blob->bucket_id),
                );
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        $this->store->blobDelete($blob->bucket_id, $blob->hash);
    }
}
