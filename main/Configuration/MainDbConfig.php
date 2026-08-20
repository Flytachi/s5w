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

    /**
     * База стоит на другом хосте, и это меняет всё остальное здесь.
     *
     * Простаивающее соединение через NAT или межсетевой экран рано или поздно
     * обрывают, не сказав об этом ни одной стороне. Без проверки такое соединение
     * доживает в пуле до первого утреннего запроса и роняет именно его. Уборщик
     * пула щупает простаивающие раз в минуту и выбрасывает мёртвые заранее.
     */
    public float $keepaliveTime = 60.0;

    /**
     * Ночью запросов нет, а десять открытых сессий на удалённом Postgres висят.
     * Лишние закрываем через пять минут простоя, до порога {@see $minimumIdle}.
     */
    public float $idleTimeout = 300.0;

    /**
     * Два соединения держим тёплыми всегда: до удалённого хоста рукопожатие — это
     * сеть, а не память, и платить им за первый запрос после тишины незачем.
     */
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
