<?php

declare(strict_types=1);

namespace Main\Enum;

enum TokenStatus: int
{
    case INACTIVE = 0;
    case ACTIVE = 1;

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
