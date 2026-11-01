<?php

declare(strict_types=1);

namespace Main\Sweepers\Expired;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Main\Services\UploadService;
use Main\Sweepers\Sweeper;

/**
 * Убирает брошенные загрузки. Живой сессию делает не возраст, а последний кусок:
 * каждый принятый кусок сдвигает срок вперёд, поэтому загрузка, идущая третьи
 * сутки, для этого прохода не просрочена.
 */
#[Singleton]
final class ExpiredUploadsSweeper implements Sweeper
{
    private const int BATCH = 500;

    private const int MAX_BATCHES = 20;

    /** Файл без сессии сносим только после простоя: в свежий может идти кусок. */
    private const int STRAY_IDLE = 3600;

    #[Autowired]
    private UploadService $uploads;

    public function sweep(bool $force = false): int
    {
        $removed = 0;

        for ($pass = 0; $pass < self::MAX_BATCHES; $pass++) {
            $purged = $this->uploads->purgeExpired(self::BATCH);
            $removed += $purged;

            if ($purged === 0) {
                break;
            }
        }

        return $removed + $this->uploads->purgeStray(self::STRAY_IDLE);
    }
}
