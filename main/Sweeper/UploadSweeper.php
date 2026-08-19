<?php

declare(strict_types=1);

namespace Main\Sweeper;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Schedule\Scheduled;
use Main\Service\UploadService;
use Psr\Log\LoggerInterface;

/**
 * Убирает брошенные загрузки. Живой сессию делает не возраст, а последний кусок:
 * каждый принятый кусок сдвигает срок вперёд, поэтому загрузка, идущая третьи
 * сутки, для этого прохода не просрочена.
 */
#[Singleton]
final class UploadSweeper
{
    private const int BATCH = 500;

    private const int MAX_BATCHES = 20;

    /** Файл без сессии сносим только после простоя: в свежий может идти кусок. */
    private const int STRAY_IDLE = 3600;

    #[Autowired]
    private UploadService $uploads;

    #[Autowired]
    private SweepLock $lock;

    #[Autowired]
    private LoggerInterface $log;

    #[Scheduled(cron: '0 * * * *')]
    public function run(): void
    {
        $this->lock->guard('uploads', function (): void {
            $removed = $this->sweep();
            if ($removed > 0) {
                $this->log->info("removed {$removed} abandoned upload(s)");
            }

            $stray = $this->uploads->purgeStray(self::STRAY_IDLE);
            if ($stray > 0) {
                $this->log->info("removed {$stray} staging file(s) with no upload behind them");
            }
        });
    }

    public function sweep(): int
    {
        $removed = 0;

        for ($pass = 0; $pass < self::MAX_BATCHES; $pass++) {
            $purged = $this->uploads->purgeExpired(self::BATCH);
            $removed += $purged;

            if ($purged === 0) {
                break;
            }
        }

        return $removed;
    }
}
