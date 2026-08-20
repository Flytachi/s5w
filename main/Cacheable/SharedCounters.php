<?php

declare(strict_types=1);

namespace Main\Cacheable;

use Swoole\Table;

/**
 * Счётчики в общей памяти воркеров: копятся здесь, в базу списываются пачкой.
 *
 * Для того, что нужно считать на каждом запросе, но записывать в базу на каждом
 * запросе нельзя. Разница измерена на pgbench, 16 писателей: в **одну** строку —
 * 1 390 tps при задержке 11.5 мс, по разным строкам — 10 216 tps при 1.57 мс. Счётчик
 * бакета это ровно случай одной строки: все скачивания из бакета бьются за неё, и она
 * стала бы самым узким местом сервиса — 1 390 rps ниже, чем весь путь `/v1/check`
 * выдаёт сегодня. Плюс каждое `+= n` оставляет мёртвую версию строки и работу для
 * autovacuum: 92 793 обновления раздули таблицу из ста строк до 184 КБ.
 *
 * {@see Table::incr()} стоит 0.33 мкс — замерено, три миллиона операций в секунду,
 * то есть 0.4% от одного запроса в базу.
 */
final class SharedCounters
{
    /** Предел ключа в Swoole\Table — 63 байта; храним ключ в колонке, берём с запасом. */
    private const int KEY_BYTES = 64;

    private ?Table $table = null;

    private bool $warned = false;

    /**
     * @param string $name Имя для журнала.
     * @param int $rows Сколько ключей помещается. Полезных будет около 69% от этого.
     */
    public function __construct(
        private readonly string $name,
        private readonly int $rows,
    ) {
    }

    /** Обязана быть вызвана в мастере до форка воркеров — иначе счётчик у каждого свой. */
    public function create(): void
    {
        if ($this->table !== null) {
            return;
        }

        $table = new Table($this->rows);
        // Исходный ключ живёт в самой таблице, а не в массиве процесса: копит воркер,
        // а сливает планировщик — это другой процесс, и его массив был бы пуст.
        $table->column('key', Table::TYPE_STRING, self::KEY_BYTES);
        $table->column('value', Table::TYPE_INT, 8);
        $table->create();

        $this->table = $table;
    }

    public function add(string $key, int $delta): void
    {
        if ($this->table === null || $delta === 0) {
            return;
        }

        if (strlen($key) > self::KEY_BYTES) {
            $this->warn(sprintf('key of %d bytes does not fit the %d-byte column', strlen($key), self::KEY_BYTES));
            return;
        }

        $slot = $this->slot($key);
        if (!$this->table->exists($slot) && $this->table->count() >= $this->capacity()) {
            // Мест нет, а выбрасывать нечего: у счётчика не бывает протухших записей.
            // Молча терять учёт нельзя — про такое надо знать.
            $this->warn(sprintf('full at %d keys, "%s" is no longer counted', $this->table->count(), $key));
            return;
        }

        $this->table->incr($slot, 'value', $delta);
        // Частичный set обновляет только названную колонку — проверено, значение
        // счётчика при этом уцелевает. Пишем каждый раз: это дешевле, чем ветвление,
        // и снимает гонку «создали строку, но подписать не успели».
        $this->table->set($slot, ['key' => $key]);
    }

    /**
     * Забирает накопленное и обнуляет — атомарно относительно параллельных {@see add()}.
     *
     * Обнуление сделано вычитанием прочитанного, а не записью нуля: между чтением и
     * записью другой воркер успевает добавить, и присвоенный ноль стёр бы его вклад.
     * Вычитается ровно столько, сколько унесли, поэтому чужая добавка остаётся в
     * таблице и уедет со следующим сливом.
     *
     * @return array<string, int> ключ => сколько накопилось; нулевые не возвращаются
     */
    public function drain(): array
    {
        if ($this->table === null) {
            return [];
        }

        $taken = [];
        foreach ($this->table as $slot => $row) {
            $value = (int) $row['value'];
            if ($value <= 0) {
                continue;
            }

            $this->table->decr((string) $slot, 'value', $value);

            $key = (string) $row['key'];
            if ($key !== '') {
                $taken[$key] = $value;
            }
        }

        return $taken;
    }

    /** Swoole наполняет хеш не до конца, поэтому за полную считаем таблицу заранее. */
    private function capacity(): int
    {
        return (int) ($this->rows * 0.65);
    }

    /** Ключ произвольной длины — в короткий: Swoole\Table отвергает длиннее 63 байт. */
    private function slot(string $key): string
    {
        return substr(hash('sha256', $this->name . '|' . $key), 0, 32);
    }

    /** Ругаемся один раз за жизнь процесса: беда постоянная, а лог не резиновый. */
    private function warn(string $message): void
    {
        if ($this->warned) {
            return;
        }

        $this->warned = true;
        error_log(sprintf('[counters:%s] %s', $this->name, $message));
    }
}
