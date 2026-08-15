<?php

declare(strict_types=1);

namespace Main\Enum;

enum Retention: int
{
    case NONE = 0;
    case DAY = 1;
    case WEEK = 2;
    case MONTH = 3;
    case QUARTER = 4;
    case HALF_YEAR = 5;
    case YEAR = 6;

    public function interval(): ?\DateInterval
    {
        return match ($this) {
            self::NONE => null,
            self::DAY => new \DateInterval('P1D'),
            self::WEEK => new \DateInterval('P7D'),
            self::MONTH => new \DateInterval('P1M'),
            self::QUARTER => new \DateInterval('P3M'),
            self::HALF_YEAR => new \DateInterval('P6M'),
            self::YEAR => new \DateInterval('P1Y'),
        };
    }

    /**
     * @return array{id: int, name: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->value,
            'name' => $this->name,
        ];
    }
}
