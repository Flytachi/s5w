<?php

declare(strict_types=1);

namespace Main\Http;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;
use Flytachi\Winter\Kernel\Http\Middleware\MiddlewareException;

/**
 * Откуда принимается API админки: `ADMIN_API=public` — отовсюду, `ADMIN_API=local` —
 * только с loopback, то есть от фронта в том же контейнере.
 *
 * Без явного значения режим выводится из `ADMIN_UI`: есть своя панель — `local`, ей
 * внешний `/admin` не нужен; панели нет — `public`, иначе админкой не воспользоваться.
 *
 * Снаружи в режиме `local` админки как будто нет: 404, а не 403, чтобы не подтверждать,
 * что она здесь есть. Решает адрес сокета — его не подделать заголовком.
 */
final class AdminNetwork
{
    private function __construct()
    {
    }

    public static function guard(HttpRequest $request): void
    {
        if (self::isLocalOnly() && !Peer::isLoopback(Peer::address($request))) {
            MiddlewareException::throw('Not Found', HttpCode::NOT_FOUND);
        }
    }

    public static function isLocalOnly(): bool
    {
        return self::mode() === 'local';
    }

    /** @return 'local'|'public' */
    public static function mode(): string
    {
        $mode = strtolower(trim((string) env('ADMIN_API', '')));
        if ($mode === 'local' || $mode === 'public') {
            return $mode;
        }

        return env('ADMIN_UI', true) ? 'local' : 'public';
    }
}
