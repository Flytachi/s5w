<?php

declare(strict_types=1);

namespace Main\Http;

use Flytachi\Winter\Kernel\Http\Cookie\Cookie;
use Flytachi\Winter\Kernel\Http\Cookie\SameSite;
use Flytachi\Winter\Kernel\Http\Cookie\SetCookie;
use Main\Support\AdminSession;

/**
 * Кука сессии панели.
 *
 * Разбор заголовка и сборка `Set-Cookie` — дело ядра ({@see Cookie}); здесь остаётся
 * только то, что ядро знать не может: имя, срок жизни и то, что кука предназначена
 * одному сайту. `Secure` выставляет {@see Cookie::make()} по схеме запроса — сам по
 * себе объект куки её не видит, а угаданный не в ту сторону флаг браузер молча
 * выбрасывает.
 */
final class AdminCookie
{
    public const string NAME = 's5w_admin';

    /** Кука на сессию: значение и срок совпадают с выданным токеном. */
    public static function issue(string $token): SetCookie
    {
        return self::base()->value($token)->expiresIn(AdminSession::TTL);
    }

    /** Та же кука с нулевым сроком — браузеру этого достаточно, чтобы её забыть. */
    public static function drop(): SetCookie
    {
        return self::base()->expiresIn(0);
    }

    public static function read(): ?string
    {
        return Cookie::get(self::NAME);
    }

    private static function base(): SetCookie
    {
        return Cookie::make(self::NAME)
            ->path('/')
            ->httpOnly()
            ->sameSite(SameSite::Strict);
    }
}
