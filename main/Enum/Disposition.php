<?php

declare(strict_types=1);

namespace Main\Enum;

enum Disposition: int
{
    case INLINE = 0;
    case ATTACHMENT = 1;

    public function isAttachment(): bool
    {
        return $this === self::ATTACHMENT;
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
