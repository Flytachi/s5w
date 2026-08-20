<?php

declare(strict_types=1);

namespace Main\Dto;

/**
 * Сутки статистики. Заполняется PDO через FETCH_CLASS, поэтому свойства публичные
 * и без конструктора: имена совпадают с псевдонимами столбцов запроса.
 */
final class TrafficDay
{
    public string $day = '';
    public int $egress = 0;
    public int $ingress = 0;
    public int $deliveries = 0;
    public int $api = 0;

    public static function empty(string $day): self
    {
        $row = new self();
        $row->day = $day;

        return $row;
    }

    private const array SHORT = ['', 'янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];

    private const array FULL = ['', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
        'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];

    /** Короткая подпись под столбиком: «14», а для первого дня месяца — «1 авг». */
    public function label(): string
    {
        [, $m, $d] = array_map('intval', explode('-', $this->day));

        return $d === 1 ? $d . ' ' . self::SHORT[$m] : (string) $d;
    }

    /** Заголовок подсказки: «14 августа 2026». */
    public function title(): string
    {
        [$y, $m, $d] = array_map('intval', explode('-', $this->day));

        return $d . ' ' . self::FULL[$m] . ' ' . $y;
    }
}
