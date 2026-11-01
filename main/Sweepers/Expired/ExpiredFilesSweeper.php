<?php

declare(strict_types=1);

namespace Main\Sweepers\Expired;

use Flytachi\Winter\Cdo\Qb;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Main\Entities\FileEntry;
use Main\Repositories\FileEntryRepository;
use Main\Services\FileService;
use Main\Sweepers\Sweeper;
use Psr\Log\LoggerInterface;

#[Singleton]
final class ExpiredFilesSweeper implements Sweeper
{
    private const int BATCH = 500;
    private const int MAX_BATCHES = 20;

    #[Autowired]
    private FileEntryRepository $repo;

    #[Autowired]
    private FileService $files;

    #[Autowired]
    private LoggerInterface $log;

    public function sweep(bool $force = false): int
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
                    $this->log->error("failed to remove expired file {$file->id}: {$e->getMessage()}");
                }
            }

            if (count($expired) < self::BATCH) {
                break;
            }
        }

        return $removed;
    }
}
