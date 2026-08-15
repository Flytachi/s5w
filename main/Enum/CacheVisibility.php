<?php

declare(strict_types=1);

namespace Main\Enum;

enum CacheVisibility: int
{
    case PUBLIC = 1;
    case PRIVATE = 2;
    case NO_STORE = 3;

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
