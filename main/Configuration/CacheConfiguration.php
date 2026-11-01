<?php

declare(strict_types=1);

namespace Main\Configuration;

use Flytachi\Winter\Kernel\Cache\CacheConfigurer;
use Flytachi\Winter\Kernel\Cache\Caches;

final class CacheConfiguration implements CacheConfigurer
{
    public const string TOKENS = 'tokens';

    /**
     * Токены: 4096 строк — с запасом, токенов на бакет единицы. 1 КБ на значение:
     * `serialize(AccessToken)` в худшем случае (имя в 100 символов кириллицей) — 630 байт.
     */
    public function configureCaches(Caches $caches): void
    {
        $caches->table(self::TOKENS, rows: 4096, valueSize: 1024, ttl: 60);
    }
}
