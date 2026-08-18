<?php

declare(strict_types=1);

namespace Main\Sweeper;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Schedule\Scheduled;
use Main\Entity\Blob;
use Main\Entity\Bucket;
use Main\Repository\BlobRepository;
use Main\Repository\BucketRepository;
use Main\Repository\FileEntryRepository;
use Psr\Log\LoggerInterface;

#[Singleton]
final class QuotaReconciler
{
    #[Autowired]
    private BucketRepository $buckets;

    #[Autowired]
    private BlobRepository $blobs;

    #[Autowired]
    private FileEntryRepository $files;

    #[Autowired]
    private SweepLock $lock;

    #[Autowired]
    private LoggerInterface $log;

    #[Scheduled(cron: '20 4 * * *')]
    public function run(): void
    {
        $this->lock->guard('quota', function (): void {
            $refs = $this->fixRefCounts();
            if ($refs > 0) {
                $this->log->warning("ref_count corrected for {$refs} blob(s)");
            }

            $bytes = $this->fixUsedBytes();
            if ($bytes > 0) {
                $this->log->warning("used_bytes corrected for {$bytes} bucket(s)");
            }
        });
    }

    public function sweep(): int
    {
        return $this->fixRefCounts() + $this->fixUsedBytes();
    }

    public function fixRefCounts(): int
    {
        $sql = sprintf(
            'UPDATE %1$s b SET ref_count = s.total FROM ('
            . ' SELECT bl.id, count(f.id)::int AS total'
            . ' FROM %1$s bl LEFT JOIN %2$s f ON f.blob_id = bl.id'
            . ' GROUP BY bl.id) s'
            . ' WHERE s.id = b.id AND b.ref_count <> s.total RETURNING b.id',
            $this->blobs->originTable(),
            $this->files->originTable(),
        );

        return count($this->blobs->rawFetch($sql, [], Blob::class));
    }

    public function fixUsedBytes(): int
    {
        $sql = sprintf(
            'UPDATE %1$s b SET used_bytes = s.total, updated_at = now() FROM ('
            . ' SELECT b2.id, coalesce(sum(bl.size_bytes), 0)::bigint AS total'
            . ' FROM %1$s b2 LEFT JOIN %2$s bl ON bl.bucket_id = b2.id'
            . ' GROUP BY b2.id) s'
            . ' WHERE s.id = b.id AND b.used_bytes <> s.total RETURNING b.id',
            $this->buckets->originTable(),
            $this->blobs->originTable(),
        );

        return count($this->buckets->rawFetch($sql, [], Bucket::class));
    }
}
