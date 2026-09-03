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

    private static ?SharedCounters $traffic = null;

    private function __construct()
    {
    }

    /** Зовётся из {@see \Main\Configuration\WebConfiguration}, до запуска сервера. */
    public static function boot(): void
    {
        self::tokens()->create();
        self::traffic()->create();
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
     * Счётчики расхода бакетов.
     *
     * Ключей четыре на бакет — по одному на метрику, — а Swoole\Table наполняется
     * примерно на 69% от заявленного. 16384 строки это около 10 600 полезных, то есть
     * 2 600 бакетов. При переполнении счёт по новому ключу молча встанет, поэтому
     * {@see SharedCounters::add()} про это говорит в лог.
     */
    public static function traffic(): SharedCounters
    {
        return self::$traffic ??= new SharedCounters('traffic', 16384);
    }
}
