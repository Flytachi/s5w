<?php

declare(strict_types=1);

namespace Main\Sweeper;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Schedule\Scheduled;
use Main\Repository\BucketRepository;
use Main\Storage\BlobStore;
use Psr\Log\LoggerInterface;

#[Singleton]
final class OrphanDirSweeper
{
    private const int AGE = 3600;
    private const float MAX_SHARE = 0.25;
    private const int MIN_KEEP = 3;

    #[Autowired]
    private BucketRepository $buckets;

    #[Autowired]
    private BlobStore $store;

    #[Autowired]
    private SweepLock $lock;

    #[Autowired]
    private LoggerInterface $log;

    #[Scheduled(cron: '0 4 * * *')]
    public function run(): void
    {
        $this->lock->guard('dirs', function (): void {
            $removed = $this->sweep();
            if ($removed > 0) {
                $this->log->info("removed {$removed} orphaned bucket dir(s)");
            }
        });
    }

    public function sweep(bool $force = false): int
    {
        $root = $this->store->rootPath();
        if (!is_dir($root)) {
            return 0;
        }

        $known = array_flip(array_column($this->buckets->select('id')->findAll(), 'id'));
        if ($known === []) {
            return 0;
        }

        $edge = time() - self::AGE;
        $total = 0;
        $orphans = [];

        foreach (new \FilesystemIterator($root, \FilesystemIterator::SKIP_DOTS) as $entry) {
            /** @var \SplFileInfo $entry */
            if (!$entry->isDir()) {
                continue;
            }
            $total++;

            if (isset($known[$entry->getFilename()]) || $entry->getMTime() > $edge) {
                continue;
            }
            $orphans[] = $entry->getFilename();
        }

        $limit = max(self::MIN_KEEP, (int) ceil($total * self::MAX_SHARE));
        if (!$force && count($orphans) > $limit) {
            $this->log->warning(sprintf(
                'skipped: %d of %d bucket directories have no bucket in the database;'
                . ' re-run with --force to delete them anyway',
                count($orphans),
                $total,
            ));
            return 0;
        }

        $removed = 0;
        foreach ($orphans as $name) {
            try {
                $this->store->removeBucketDir($name);
                $removed++;
            } catch (\Throwable $e) {
                $this->log->error("failed to remove orphaned bucket directory {$name}: {$e->getMessage()}");
            }
        }

        return $removed;
    }
}
