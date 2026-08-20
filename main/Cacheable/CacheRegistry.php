<?php

declare(strict_types=1);

namespace Main\Cacheable;

/**
 * Все разделяемые кэши приложения и единственное место, где под них отводится память.
 *
 * Экземпляры лежат в статике намеренно. Контейнер собирает синглтоны лениво, то есть
 * уже внутри воркера, а {@see \Swoole\Table} обязана быть создана в мастере **до
 * форка** — иначе у каждого воркера окажется свой кэш, и отзыв токена в одном не
 * дошёл бы до остальных. Статика, заполненная на старте сервера, переживает форк и
 * достаётся всем воркерам одной и той же памятью.
 *
 * В CLI {@see boot()} не зовут: таблицы нет, кэш отвечает промахом на всё, команды и
 * уборщики работают как работали.
 */
final class CacheRegistry
{
    private static ?SharedCache $tokens = null;

    private static ?SharedCounters $egress = null;

    private function __construct()
    {
    }

    /** Зовётся из {@see \Main\Configuration\WebConfiguration}, до запуска сервера. */
    public static function boot(): void
    {
        self::tokens()->create();
        self::egress()->create();
    }

    /**
     * Кэш проверки токенов доступа.
     *
     * 4096 строк — с запасом: полезных из них около 2600, а токенов на бакет единицы.
     * 512 байт хватает на строку `access_tokens` в JSON (самое длинное в ней — `name`
     * до 100 символов и три отметки времени), и это проверяется на записи: значение
     * длиннее не кэшируется, а не обрезается.
     */
    public static function tokens(): SharedCache
    {
        return self::$tokens ??= new SharedCache('tokens', 4096, 512);
    }

    /**
     * Счётчики отданных байт по бакету.
     *
     * Ключей столько же, сколько бакетов, и живут они до ближайшего слива — 4096 строк
     * (полезных около 2600) хватает с большим запасом. Переполнение не молчаливое:
     * {@see SharedCounters::add()} скажет в лог, что учёт по ключу встал.
     */
    public static function egress(): SharedCounters
    {
        return self::$egress ??= new SharedCounters('egress', 4096);
    }
}
