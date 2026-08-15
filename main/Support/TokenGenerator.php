<?php

declare(strict_types=1);

namespace Main\Support;

final class TokenGenerator
{
    private const string PREFIX = 's5w_';

    /** 32 байта = 256 бит энтропии. */
    private const int BYTES = 32;

    /**
     * @return array{token: string, hash: string} token — клиенту один раз, hash — в базу
     */
    public static function generate(): array
    {
        $token = self::PREFIX . bin2hex(random_bytes(self::BYTES));

        return ['token' => $token, 'hash' => self::hash($token)];
    }

    /** Хеш для поиска строки по предъявленному токену. 64 hex-символа. */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Сравнение за константное время — чтобы по времени ответа не подбирался хеш. */
    public static function verify(string $token, string $storedHash): bool
    {
        return hash_equals($storedHash, self::hash($token));
    }
}
