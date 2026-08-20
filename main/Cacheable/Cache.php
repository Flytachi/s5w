<?php

declare(strict_types=1);

namespace Main\Cacheable;

/**
 * Кэш «ключ → строка» со сроком жизни.
 *
 * Интерфейс здесь ради одного: когда `#[Cacheable]` появится в ядре, замена сведётся
 * к новой реализации, а места вызова останутся как есть. Поэтому в нём нет ничего от
 * {@see \Swoole\Table} — ни размеров колонок, ни ёмкости.
 *
 * Кэш **не источник истины**. Любой метод имеет право ничего не сделать и промолчать:
 * промах стоит запроса в базу, а исключение стоило бы ответа клиенту.
 */
interface Cache
{
    /** @return string|null null — промах: ключа нет, он протух или кэш недоступен. */
    public function get(string $key): ?string;

    /**
     * @param int $ttl Срок в секундах от «сейчас».
     * @return bool Записали ли. `false` — не поместилось; это нормальный исход, не ошибка.
     */
    public function put(string $key, string $value, int $ttl): bool;

    public function forget(string $key): void;

    /** Выбрасывает всё. Нужен там, где точечная инвалидация невозможна. */
    public function flush(): void;
}
