<?php

declare(strict_types=1);

namespace Main\Services;

use Flytachi\Winter\Cdo\Qb;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Cache\Attribute\CacheConfig;
use Flytachi\Winter\Kernel\Cache\Attribute\Cacheable;
use Flytachi\Winter\Kernel\Cache\Attribute\CachePut;
use Flytachi\Winter\Kernel\Cache\CacheManager;
use Main\Configuration\CacheConfiguration;
use Main\Entities\AccessToken;
use Main\Repositories\AccessTokenRepository;

/**
 * Строка `access_tokens` по хэшу токена — через кэш.
 *
 * Зачем: проверка токена — первое, что делает каждый запрос к `/v1/*`, и она стоит
 * одного запроса в базу. Замерено `wrk` на `/v1/check`, база на петле, боевой режим:
 * без запросов в БД 18 765 rps, с одним 7 543, с двумя 5 086. То есть один запрос
 * забирает ~79 мкс из бюджета и 60% пропускной способности. С кэшем ручка дала
 * 7 396 против 5 086 — **+45%**.
 *
 * Срок жизни здесь — страховка, а не механизм. Основное — явная чистка через
 * {@see forget()} и {@see forgetAll()} на всех путях, которые лишают токен силы:
 * {@see AccessTokenService::rotate()}, `changeStatus()`, `delete()` и удаление бакета.
 * Отозванный токен, который живёт ещё минуту, — это не «слегка устаревшие данные», это
 * дыра; TTL прикрывает только случай, когда чистку забыли добавить в новый путь записи.
 *
 * Отрицательных ответов не храним (`cacheNull: false`): иначе созданный токен минуту
 * не работал бы, а от долбёжки мусорными токенами кэш всё равно не защищает — это дело
 * слоя перед сервисом.
 *
 * Класс не `final` намеренно: кэш ядра работает через прокси-наследника.
 */
#[Singleton]
#[CacheConfig(CacheConfiguration::TOKENS)]
class AccessTokenLookup
{
    #[Autowired]
    private AccessTokenRepository $repo;

    #[Cacheable(key: '{hash}', cacheNull: false)]
    public function find(string $hash): ?AccessToken
    {
        return $this->repo->findBy(Qb::eq('hash', $hash));
    }

    /**
     * Пишет отметку и в базу, и в кэш. Иначе выходит ловушка: закэшированная строка
     * держит старое `last_used_at`, каждый следующий запрос в пределах срока жизни кэша
     * снова считает её просроченной и снова пишет — то есть запись на каждый запрос в
     * одну и ту же строку. Замерено на pgbench: 16 писателей в одну строку — 1 390 tps
     * против 10 216 по разным строкам, разница в 7.3 раза плюс мёртвые версии строк и
     * работа для autovacuum.
     */
    #[CachePut(key: '{result.hash}')]
    public function touch(AccessToken $token, string $at): AccessToken
    {
        $this->repo->update(['last_used_at' => $at], Qb::eq('id', $token->id));
        $token->last_used_at = $at;

        return $token;
    }

    public function forget(string $hash): void
    {
        CacheManager::evict(CacheConfiguration::TOKENS, $hash);
    }

    /**
     * Для случаев, когда силы лишаются токены, которых мы поимённо не знаем: удаление
     * бакета уносит их каскадом в базе, а по бакету кэш не индексирован. Токенов
     * немного, а удаление бакета редкое — платить за индекс ради этого незачем.
     */
    public function forgetAll(): void
    {
        CacheManager::clear(CacheConfiguration::TOKENS);
    }
}
