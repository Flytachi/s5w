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
}
