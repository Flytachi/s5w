<?php

declare(strict_types=1);

namespace Main\Web;

/** Один столбик графика: два ряда в процентах от общей шкалы плюс всё для подсказки. */
final class Column
{
    public function __construct(
        public readonly string $day,
        public readonly string $dayTitle,
        public readonly string $label,
        public readonly float $topPercent,
        public readonly float $bottomPercent,
        public readonly string $topValue,
        public readonly string $bottomValue,
        public readonly bool $isEmpty,
    ) {
    }
}
