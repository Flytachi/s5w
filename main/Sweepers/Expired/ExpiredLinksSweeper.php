<?php

declare(strict_types=1);

namespace Main\Sweepers\Expired;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Main\Entities\ShareLink;
use Main\Repositories\ShareLinkRepository;
use Main\Sweepers\Sweeper;

#[Singleton]
final class ExpiredLinksSweeper implements Sweeper
{
    private const int BATCH = 500;
    private const int MAX_BATCHES = 20;
    private const string KEEP = '7 days';

    #[Autowired]
    private ShareLinkRepository $repo;

    public function sweep(bool $force = false): int
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
