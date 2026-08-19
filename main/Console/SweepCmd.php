<?php

declare(strict_types=1);

namespace Main\Console;

use Flytachi\Winter\Console\Inc\Cmd;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Ppa\Pool\PpaConnectionPool;
use Main\Configuration\MainDbConfig;
use Main\Storage\BlobStore;
use Main\Sweeper\OrphanBlobSweeper;
use Main\Sweeper\OrphanDirSweeper;
use Main\Sweeper\QuotaReconciler;
use Main\Sweeper\RetentionSweeper;
use Main\Sweeper\ShareLinkSweeper;
use Main\Sweeper\UploadSweeper;

final class SweepCmd extends Cmd
{
    public static string $title = 'run the sweepers by hand';

    #[Autowired]
    private BlobStore $store;

    #[Autowired]
    private RetentionSweeper $retention;

    #[Autowired]
    private ShareLinkSweeper $links;

    #[Autowired]
    private OrphanBlobSweeper $blobs;

    #[Autowired]
    private OrphanDirSweeper $dirs;

    #[Autowired]
    private QuotaReconciler $quota;

    #[Autowired]
    private UploadSweeper $uploads;

    public function handle(): void
    {
        $only = $this->args['arguments'][1] ?? 'all';

        self::printInfo('storage: ' . $this->store->rootPath());
        self::printInfo('database: ' . PpaConnectionPool::getConfigDb(MainDbConfig::class)->getDns());

        $jobs = [
            'retention' => fn() => $this->retention->sweep(),
            'links' => fn() => $this->links->sweep(),
            'blobs' => fn() => $this->blobs->sweep(),
            'blobs-disk' => fn() => $this->blobs->sweepDisk(),
            'dirs' => fn() => $this->dirs->sweep($this->forced()),
            'quota' => fn() => $this->quota->sweep(),
            'uploads' => fn() => $this->uploads->sweep(),
        ];

        if ($only !== 'all' && !isset($jobs[$only])) {
            self::printWarning('unknown sweeper: ' . $only);
            return;
        }

        foreach ($jobs as $name => $job) {
            if ($only !== 'all' && $only !== $name) {
                continue;
            }
            self::printSuccess(sprintf('%-12s %d', $name, $job()));
        }
    }

    private function forced(): bool
    {
        return array_key_exists('force', $this->args['options'])
            || in_array('f', $this->args['flags'], true);
    }

    public static function help(): void
    {
        self::printInfo('call sc main.Console.SweepCmd [retention|links|blobs|blobs-disk|dirs|quota|uploads] [--force]');
    }
}
