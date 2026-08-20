<?php

declare(strict_types=1);

namespace Main\Request;

/**
 * Отрезок для вкладки статистики.
 *
 * Обе границы — даты в поясе смотрящего, включительно. По умолчанию последние
 * тридцать дней: месяц это то, на что смотрят почти всегда, а «сегодня» в одиночку
 * почти ничего не говорит.
 */
final class TrafficRangeRequest
{
    private const int DEFAULT_DAYS = 29;

    /** Дальше года назад данных нет — их убирает {@see \Main\Sweeper\TrafficSweeper}. */
    private const int MAX_DAYS = 366;

    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
    ) {
    }

    /**
     * Границы, приведённые к порядку и к разумной длине.
     *
     * Правится молча, а не отвергается — поэтому и проверки атрибутом здесь нет.
     * Перепутанные местами даты, запрошенные десять лет, мусор вместо даты — это
     * опечатка в адресной строке, и отдать за неё 422 на странице панели значит
     * завести пользователя в тупик вместо соседнего разумного отрезка.
     *
     * @return array{0: string, 1: string}
     */
    public function resolve(\DateTimeZone $tz): array
    {
        $today = new \DateTimeImmutable('now', $tz);
        $to = $this->parse($this->to, $tz) ?? $today;
        $from = $this->parse($this->from, $tz) ?? $to->modify('-' . self::DEFAULT_DAYS . ' days');

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        if ($from < $to->modify('-' . self::MAX_DAYS . ' days')) {
            $from = $to->modify('-' . self::MAX_DAYS . ' days');
        }

        return [$from->format('Y-m-d'), $to->format('Y-m-d')];
    }

    private function parse(?string $value, \DateTimeZone $tz): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $tz);

        return $date === false ? null : $date;
    }
}
