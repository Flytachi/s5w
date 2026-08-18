<?php

declare(strict_types=1);

namespace Main\Sweeper;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Schedule\Scheduled;
use Main\Entity\ShareLink;
use Main\Repository\ShareLinkRepository;
use Psr\Log\LoggerInterface;

#[Singleton]
final class ShareLinkSweeper
{
    private const int BATCH = 500;
    private const int MAX_BATCHES = 20;
    private const string KEEP = '7 days';

    #[Autowired]
    private ShareLinkRepository $repo;

    #[Autowired]
    private SweepLock $lock;

    #[Autowired]
    private LoggerInterface $log;

    #[Scheduled(cron: '15 3 * * *')]
    public function run(): void
    {
        $this->lock->guard('links', function (): void {
            $removed = $this->sweep();
            if ($removed > 0) {
                $this->log->info("removed {$removed} dead link(s)");
            }
        });
    }

    public function sweep(): int
    {
        $table = $this->repo->originTable();
        $sql = sprintf(
            'DELETE FROM %1$s WHERE id IN ('
            . ' SELECT id FROM %1$s'
            . " WHERE expires_at <= now() - interval '%2\$s'"
            . ' ORDER BY id LIMIT %3$d) RETURNING id',
            $table,
            self::KEEP,
            self::BATCH,
        );

        $removed = 0;

        for ($pass = 0; $pass < self::MAX_BATCHES; $pass++) {
            $rows = $this->repo->rawFetch($sql, [], ShareLink::class);
            $removed += count($rows);

            if (count($rows) < self::BATCH) {
                break;
            }
        }

        return $removed;
    }
}
