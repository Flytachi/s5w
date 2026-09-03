<?php

declare(strict_types=1);

namespace Main\Cacheable;

use Swoole\Table;

/**
 * {@see Cache} поверх {@see Table} — общей памяти воркеров.
 *
 * Почему не Redis: замерено на этом приложении — запрос в Postgres по уникальному
 * индексу стоит 79 мкс из бюджета запроса, `Table::incr` — 0.33 мкс, а Redis по петле
 * это всё-таки сокет, пул и протокол. Пока контейнер один, общая память быстрее и не
 * приносит второго сервиса, за которым надо присматривать. Появится второй контейнер —
 * меняется реализация {@see Cache}, вызовы остаются.
 *
 * У `Swoole\Table` три края, на каждый из которых здесь стоит заплатка, потому что все
 * три она проходит молча (проверено):
 *
 *   1. Ключ длиннее 63 байт она отвергает целиком — `set()` пишет WARNING и не пишет
 *      строку. Наш ключ токена это sha256 в hex, то есть ровно 64 символа: без своего
 *      укорачивания кэш не работал бы вообще, и никто бы этого не заметил.
 *   2. Значение длиннее колонки обрезается, а `set()` возвращает **true**. Обрезанный
 *      JSON потом развернётся в мусор — худший вид промаха, потому что он неотличим
 *      от попадания. Длину проверяем сами.
 *   3. Таблица заполняется примерно на 69% от заявленного числа строк, после чего
 *      `set()` навсегда отвечает false, а вытеснения у неё нет. То есть переполненный
 *      кэш держит протухшее и не принимает новое. Отсюда {@see sweep()}.
 */
final class SharedCache implements Cache
{
    /** Предел ключа в Swoole\Table — 63 байта; берём вдвое короче, чтобы не гадать. */
    private const int KEY_CHARS = 32;

    private ?Table $table = null;

    private bool $warned = false;

    /**
     * @param string $name Имя для журнала и для разведения таблиц.
     * @param int $rows Сколько строк объявляем. Полезных будет около 69% от этого.
     * @param int $valueBytes Потолок значения; длиннее — не кэшируем.
     */
    public function __construct(
        private readonly string $name,
        private readonly int $rows,
        private readonly int $valueBytes,
    ) {
    }

    /**
     * Отводит память под таблицу.
     *
     * Обязана быть вызвана **в мастере до форка воркеров** — иначе память достанется
     * тому процессу, который её отвёл, и каждый воркер получит свой отдельный кэш,
     * который к тому же не увидит чужой инвалидации.
     */
    public function create(): void
    {
        if ($this->table !== null) {
            return;
        }

        $table = new Table($this->rows);
        $table->column('value', Table::TYPE_STRING, $this->valueBytes);
        $table->column('expires', Table::TYPE_INT, 8);
        $table->create();

        $this->table = $table;
    }

    /** Отведена ли память. В CLI — нет, и тогда кэш ведёт себя как вечный промах. */
    public function isReady(): bool
    {
        return $this->table !== null;
    }

    public function get(string $key): ?string
    {
        if ($this->table === null) {
            return null;
        }

        $row = $this->table->get($this->hash($key));
        if ($row === false) {
            return null;
        }

        if ($row['expires'] <= time()) {
            // Протухшее убираем сразу: строка занимает место, а места мало.
            $this->table->del($this->hash($key));
            return null;
        }

        return $row['value'];
    }

    public function put(string $key, string $value, int $ttl): bool
    {
        if ($this->table === null || $ttl <= 0) {
            return false;
        }

        if (strlen($value) > $this->valueBytes) {
            // Обрезать нельзя: обрезанное значение вернётся как попадание.
            $this->warn(sprintf('value of %d bytes exceeds the %d-byte column', strlen($value), $this->valueBytes));
            return false;
        }

        $slot = $this->hash($key);
        if (!$this->table->exists($slot) && $this->table->count() >= $this->capacity()) {
            $this->sweep();
        }

        return $this->table->set($slot, ['value' => $value, 'expires' => time() + $ttl]);
    }

    public function forget(string $key): void
    {
        $this->table?->del($this->hash($key));
    }

    public function flush(): void
    {
        if ($this->table === null) {
            return;
        }

        foreach ($this->table as $slot => $_) {
            $this->table->del((string) $slot);
        }
    }

    /**
     * Выбрасывает протухшее, освобождая место под новое.
     *
     * Это не вытеснение по давности: если протухшего не нашлось, кэш просто перестанет
     * принимать новые ключи до истечения сроков у старых. Для нескольких сотен токенов
     * этого достаточно; когда понадобится LRU, он приедет вместе с ядерным
     * `#[Cacheable]`, а не будет писаться здесь второй раз.
     */
    private function sweep(): void
    {
        if ($this->table === null) {
            return;
        }

        $now = time();
        $freed = 0;
        foreach ($this->table as $slot => $row) {
            if ($row['expires'] <= $now) {
                $this->table->del((string) $slot);
                $freed++;
            }
        }

        if ($freed === 0) {
            $this->warn(sprintf('full at %d rows, nothing expired to evict', $this->table->count()));
        }
    }

    /** Swoole наполняет хеш не до конца, поэтому за полную считаем таблицу заранее. */
    private function capacity(): int
    {
        return (int) ($this->rows * 0.65);
    }

    /**
     * Ключ произвольной длины — в короткий фиксированный.
     *
     * 32 hex-символа это 128 бит: столкновений на нашем числе ключей не будет, а в
     * предел Swoole укладывается с запасом.
     */
    private function hash(string $key): string
    {
        return substr(hash('sha256', $this->name . '|' . $key), 0, self::KEY_CHARS);
    }

    /** Ругаемся один раз за жизнь воркера: беда постоянная, а лог не резиновый. */
    private function warn(string $message): void
    {
        if ($this->warned) {
            return;
        }

        $this->warned = true;
        error_log(sprintf('[cache:%s] %s', $this->name, $message));
    }
}
