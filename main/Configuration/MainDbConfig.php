<?php

declare(strict_types=1);

namespace Main\Configuration;

use Flytachi\Winter\Cdo\Config\PgDbConfig;
use Flytachi\Winter\Ppa\Mapping\Attributes\Config\Migratable;
use Flytachi\Winter\Ppa\Pool\PpaPoolConfigInterface;
use Flytachi\Winter\Ppa\Pool\PpaPoolTrait;

#[Migratable]
final class MainDbConfig extends PgDbConfig implements PpaPoolConfigInterface
{
    use PpaPoolTrait;

    public int $poolMaxConnections = 10;
    public float $keepaliveTime = 60.0;
    public float $idleTimeout = 300.0;
    public int $minimumIdle = 2;

    public function setUp(): void
    {
        $this->host     = (string) env('DB_HOST', 'localhost');
        $this->port     = (int) env('DB_PORT', 5432);
        $this->database = (string) env('DB_NAME', 's5w');
        $this->username = (string) env('DB_USER', 's5w');
        $this->password = (string) env('DB_PASS', '');
        $this->schema   = (string) env('DB_SCHEMA', 'public');
    }
}
