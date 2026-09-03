<?php

declare(strict_types=1);

namespace Main\Sweeper;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Schedule\Scheduled;
use Main\Entity\BucketTraffic;
use Main\Repository\BucketTrafficRepository;
use Psr\Log\LoggerInterface;

/**
 * Выбрасывает часовые отрезки статистики старше года.
 *
 * Час на бакет это 8 760 строк в год; сто бакетов — 876 тысяч. Само по себе немного,
 * но копится вечно, а смотрят почти всегда последний месяц. Год — запас, которого
 * хватает и на «сравнить с прошлым августом».
 *
 * Границу считает база (`now() - interval '1 year'`), а не PHP. Не из вредности:
 * уборщик работает в процессе планировщика, где нет запроса, а значит и часового
 * пояса клиента; `date()` там вернёт то, что стоит в `TIME_ZONE`, и запись «год назад»
 * зависела бы от настройки окружения. У базы для этого есть свои now() и interval,
 * и они не зависят ни от чего.
 */
#[Singleton]
final class TrafficSweeper
{
    /** За раз не больше — чтобы одна уборка не держала таблицу минутами. */
    private const int BATCH = 50000;

    #[Autowired]
    private BucketTrafficRepository $repo;

    #[Autowired]
    private SweepLock $lock;

    #[Autowired]
    private LoggerInterface $log;

    #[Scheduled(cron: '10 4 * * *')]
    public function run(): void
    {
        $this->lock->guard('traffic', function (): void {
            $removed = $this->sweep();
            if ($removed > 0) {
                $this->log->info("removed {$removed} traffic row(s) older than a year");
            }
        });
    }

    public function sweep(): int
    {
        $sql = sprintf(
            'DELETE FROM %1$s WHERE id IN ('
            . ' SELECT id FROM %1$s WHERE period < now() - interval \'1 year\' LIMIT %2$d'
            . ') RETURNING id',
            $this->repo->originTable(),
            self::BATCH,
        );

        return count($this->repo->rawFetch($sql, [], BucketTraffic::class));
    }
}
