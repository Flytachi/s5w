<?php

declare(strict_types=1);

namespace Main\Enum;

enum CacheVisibility: int
{
    case SHARED = 1;
    case PRIVATE = 2;
    case NO_STORE = 3;

    public function directive(): string
    {
        return match ($this) {
            self::SHARED => 'public',
            self::PRIVATE => 'private',
            self::NO_STORE => 'no-store',
        };
    }

    public function defaultMaxAge(): int
    {
        return match ($this) {
            self::SHARED => (int) env('CACHE_DEFAULT_MAX_AGE', 86400),
            default => (int) env('CACHE_PRIVATE_MAX_AGE', 0),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SHARED => 'всем и CDN',
            self::PRIVATE => 'только клиенту',
            self::NO_STORE => 'не хранить',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::SHARED => 'ok',
            self::PRIVATE => 'brand',
            self::NO_STORE => 'warn',
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
