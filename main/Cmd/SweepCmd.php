<?php

declare(strict_types=1);

namespace Main\Cmd;

use Flytachi\Winter\Console\Inc\Cmd;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Ppa\Pool\PpaConnectionPool;
use Main\Configuration\MainDbConfig;
use Main\Storage\BlobStore;
use Main\Threads\Schedule\Maintenance;

final class SweepCmd extends Cmd
{
    public static string $title = 'run the sweepers by hand';

    #[Autowired]
    private BlobStore $store;

    #[Autowired]
    private Maintenance $maintenance;

    public function handle(): void
    {
        $only = $this->args['arguments'][1] ?? 'all';
        $sweepers = $this->maintenance->sweepers();

        self::printInfo('storage: ' . $this->store->rootPath());
        self::printInfo('database: ' . PpaConnectionPool::getConfigDb(MainDbConfig::class)->getDns());

        if ($only !== 'all' && !isset($sweepers[$only])) {
            self::printWarning('unknown sweeper: ' . $only);
            return;
        }

        foreach ($sweepers as $name => $sweeper) {
            if ($only !== 'all' && $only !== $name) {
                continue;
            }
            self::printSuccess(sprintf('%-12s %d', $name, $sweeper->sweep($this->forced())));
        }
    }

    private function forced(): bool
    {
        return array_key_exists('force', $this->args['options'])
            || in_array('f', $this->args['flags'], true);
    }

    public static function help(): void
    {
        self::printInfo('call sc main.Cmd.SweepCmd [retention|uploads|links|blobs|blobs-disk|dirs|traffic|quota] [--force]');
    }
}
