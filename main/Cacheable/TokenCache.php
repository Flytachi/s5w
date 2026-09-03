<?php

declare(strict_types=1);

namespace Main\Cacheable;

use Flytachi\Winter\DI\Attribute\Singleton;
use Main\Entity\AccessToken;

/**
 * Кэш строки `access_tokens` по хэшу токена.
 *
 * Зачем: проверка токена — первое, что делает каждый запрос к `/v1/*`, и она стоит
 * одного запроса в базу. Замерено `wrk` на `/v1/check`, база на петле, боевой режим:
 * без запросов в БД 18 765 rps, с одним 7 543, с двумя 5 086. То есть один запрос
 * забирает ~79 мкс из бюджета и 60% пропускной способности. С этим кэшем ручка дала
 * 7 396 против 5 086 — **+45%**.
 *
 * Срок жизни здесь — страховка, а не механизм. Основное — явная чистка на всех путях,
 * которые лишают токен силы: {@see \Main\Service\AccessTokenService::rotate()},
 * `changeStatus()`, `delete()` и удаление бакета. Отозванный токен, который живёт ещё
 * минуту, — это не «слегка устаревшие данные», это дыра; TTL прикрывает только случай,
 * когда чистку забыли добавить в новый путь записи.
 *
 * Отрицательных ответов не храним: иначе созданный токен минуту не работал бы, а от
 * долбёжки мусорными токенами кэш всё равно не защищает — это дело слоя перед сервисом.
 */
#[Singleton]
final class TokenCache
{
    private const int TTL = 60;

    private SharedCache $store;

    public function __construct()
    {
        $this->store = CacheRegistry::tokens();
    }

    public function get(string $hash): ?AccessToken
    {
        $raw = $this->store->get($hash);
        if ($raw === null) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            // Читать нечего — ведём себя как промах, а не падаем на пути авторизации.
            $this->store->forget($hash);
            return null;
        }

        $token = new AccessToken();
        foreach ($data as $field => $value) {
            if (property_exists($token, $field)) {
                $token->$field = $value;
            }
        }

        return $token;
    }

    public function put(AccessToken $token): void
    {
        $json = json_encode(get_object_vars($token), JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }

        $this->store->put($token->hash, $json, self::TTL);
    }

    public function forget(string $hash): void
    {
        $this->store->forget($hash);
    }

    /**
     * Выбрасывает весь кэш.
     *
     * Для случаев, когда силы лишаются токены, которых мы поимённо не знаем: удаление
     * бакета уносит их каскадом в базе, а по бакету кэш не индексирован. Токенов
     * немного, а удаление бакета редкое — платить за индекс ради этого незачем.
     */
    public function flush(): void
    {
        $this->store->flush();
    }
}
