<?php

declare(strict_types=1);

namespace Main\Support;

final class TokenGenerator
{
    private const string PREFIX = 's5w_';

    private const int BYTES = 32;

    /**
     * @return array{token: string, hash: string, tail: string}
     */
    public static function generate(): array
    {
        $token = self::PREFIX . bin2hex(random_bytes(self::BYTES));

        return ['token' => $token, 'hash' => self::hash($token), 'tail' => self::tail($token)];
    }

    public static function tail(string $token): string
    {
        return substr($token, -4);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function verify(string $token, string $storedHash): bool
    {
        return hash_equals($storedHash, self::hash($token));
    }
}
