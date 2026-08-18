<?php

declare(strict_types=1);

namespace Main\Sweeper;

use Flytachi\Winter\Cdo\Qb;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Schedule\Scheduled;
use Main\Entity\FileEntry;
use Main\Repository\FileEntryRepository;
use Main\Service\FileService;
use Psr\Log\LoggerInterface;

#[Singleton]
final class RetentionSweeper
{
    private const int BATCH = 500;
    private const int MAX_BATCHES = 20;

    #[Autowired]
    private FileEntryRepository $repo;

    #[Autowired]
    private FileService $files;

    #[Autowired]
    private SweepLock $lock;

    #[Autowired]
    private LoggerInterface $log;

    #[Scheduled(fixedDelay: 300.0, initialDelay: 60.0)]
    public function run(): void
    {
        $this->lock->guard('retention', function (): void {
            $removed = $this->sweep();
            if ($removed > 0) {
                $this->log->info("removed {$removed} expired file(s)");
            }
        });
    }

    public function sweep(): int
    {
        $removed = 0;

        for ($pass = 0; $pass < self::MAX_BATCHES; $pass++) {
            $expired = $this->repo
                ->where(Qb::raw('expires_at IS NOT NULL AND expires_at <= now()'))
                ->orderBy('id')
                ->limit(self::BATCH)
                ->findAll();

            if ($expired === []) {
                break;
            }

            foreach ($expired as $file) {
                /** @var FileEntry $file */
                try {
                    $this->files->remove($file);
                    $removed++;
                } catch (\Throwable $e) {
                    $this->log->error("file {$file->id} left behind: {$e->getMessage()}");
                }
            }

            if (count($expired) < self::BATCH) {
                break;
            }
        }

        return $removed;
    }
}
