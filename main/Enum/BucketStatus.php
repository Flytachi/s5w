<?php

declare(strict_types=1);

namespace Main\Enum;

enum BucketStatus: int
{
    case CREATED = 0;
    case PENDING = 1;
    case INACTIVE = 2;
    case ACTIVE = 3;

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
