<?php

declare(strict_types=1);

use Flytachi\Winter\Kernel\App\Attribute\EnableAsync;
use Flytachi\Winter\Kernel\App\Attribute\EnableCaching;
use Flytachi\Winter\Kernel\App\Attribute\EnableHttp;
use Flytachi\Winter\Kernel\App\Attribute\EnableScheduler;
use Flytachi\Winter\Kernel\App\Components;
use Flytachi\Winter\Kernel\WinterApplication;
use Main\Threads\Process\AdminUi;

require __DIR__ . '/vendor/autoload.php';

#[EnableHttp(port: 9090)]
#[EnableAsync]
#[EnableCaching]
#[EnableScheduler]
final class Application extends WinterApplication
{
    public static function main(array $argv): never
    {
        parent::run($argv);
    }

    protected static function components(Components $components): void
    {
        if (env('ADMIN_UI', true)) {
            $components->process(AdminUi::class, critical: false);
        }
    }
}
