<?php

declare(strict_types=1);

namespace Main\Support;

final class Slug
{
    public const int LENGTH = 16;

    private const int BYTES = 12; // 12 байт → ровно 16 символов base64url без '='

    public static function generate(): string
    {
        return self::BYTES
                |> random_bytes(...)
                |> base64_encode(...)
                |> (fn($x) => strtr($x, '+/', '-_'));
    }
}
