<?php

declare(strict_types=1);

namespace Main\Http;

use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;
use Main\Support\AdminSession;

final class AdminCookie
{
    public const string NAME = 's5w_admin';

    public static function set(HttpRequest $request, string $token): string
    {
        return self::build($token, AdminSession::TTL, $request);
    }

    public static function clear(HttpRequest $request): string
    {
        return self::build('', 0, $request);
    }

    public static function read(HttpRequest $request): ?string
    {
        $header = $request->getHeader('cookie') ?? $request->getHeader('Cookie');
        if ($header === null || $header === '') {
            return null;
        }

        foreach (explode(';', $header) as $part) {
            [$name, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($name === self::NAME) {
                return urldecode($value);
            }
        }

        return null;
    }

    private static function build(string $token, int $maxAge, HttpRequest $request): string
    {
        $parts = [
            self::NAME . '=' . $token,
            'Path=/',
            'Max-Age=' . $maxAge,
            'HttpOnly',
            'SameSite=Strict',
        ];

        if ($request->getScheme() === 'https') {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }
}
