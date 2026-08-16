<?php

declare(strict_types=1);

namespace Main\Enum;

enum ImageFormat: string
{
    case ORIGINAL = 'ORIGINAL';
    case WEBP = 'WEBP';
    case JPEG = 'JPEG';
    case PNG = 'PNG';
    case AVIF = 'AVIF';

    public function mime(): ?string
    {
        return match ($this) {
            self::ORIGINAL => null,
            self::WEBP => 'image/webp',
            self::JPEG => 'image/jpeg',
            self::PNG => 'image/png',
            self::AVIF => 'image/avif',
        };
    }

    /**
     * Качество, когда клиент его не задал, а перекодировать всё равно надо
     * (просили формат или ресайз). Для avif шкала другая: 55 у него примерно
     * там же по картинке, где 82 у webp.
     */
    public static function defaultQuality(string $mime): int
    {
        return match ($mime) {
            'image/avif' => 55,
            'image/png' => 60,
            default => 82,
        };
    }
}
