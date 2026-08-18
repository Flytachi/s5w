<?php

declare(strict_types=1);

namespace Main\Sweeper;

use Flytachi\Winter\Cdo\CDOBind;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Main\Dto\LockRes;
use Main\Repository\BucketRepository;
use Psr\Log\LoggerInterface;

#[Singleton]
final class SweepLock
{
    #[Autowired]
    private BucketRepository $repo;

    #[Autowired]
    private LoggerInterface $log;

    public function guard(string $name, callable $work): void
    {
        $key = crc32('s5w/sweeper/' . $name);

        if (!$this->acquire($key)) {
            return;
        }

        try {
            $work();
        } catch (\Throwable $e) {
            $this->log->error("sweeper \"{$name}\" failed: {$e->getMessage()}");
        } finally {
            $this->release($key);
        }
    }

    private function acquire(int $key): bool
    {
        $rows = $this->repo->rawFetch(
            'SELECT pg_try_advisory_lock(:key)::int AS taken',
            [new CDOBind('key', $key)],
            LockRes::class,
        );

        return ($rows[0]->taken ?? 0) === 1;
    }

    private function release(int $key): void
    {
        $this->repo->rawFetch(
            'SELECT pg_advisory_unlock(:key)::int AS taken',
            [new CDOBind('key', $key)],
            LockRes::class,
        );
    }
}
