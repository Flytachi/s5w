<?php

declare(strict_types=1);

namespace Main\Enum;

enum TokenAccess: int
{
    case BASIC = 1;

    case FULL = 2;

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

    public function label(): string
    {
        return match ($this) {
            self::BASIC => 'чтение',
            self::FULL => 'полный',
        };
    }

    public function allows(self $required): bool
    {
        return $this === self::FULL || $required === self::BASIC;
    }
}
