<?php

declare(strict_types=1);

namespace Main\Http;

use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;

/**
 * Адрес клиента, которому можно верить.
 *
 * `HttpRequest::getClientIp()` берёт `Forwarded`, `X-Real-IP` и `X-Forwarded-For` у
 * любого клиента, то есть адрес подделывается одним заголовком. Здесь читается только
 * `X-Forwarded-For` (справа налево, до первого недоверенного адреса) и только если запрос
 * пришёл от доверенного прокси: от loopback (свой фронт в контейнере)
 * или от адреса из `TRUSTED_PROXIES` (через запятую, IP или CIDR — nginx, traefik перед
 * контейнером). Иначе адрес — это сокет.
 *
 * По умолчанию `private` — частные сети: прокси перед контейнером почти всегда сидит в
 * docker-сети, и настраивать ничего не нужно. `none` — не доверять никому, кроме loopback.
 */
final class Peer
{
    /** `private`: частные сети IPv4 и IPv6 (ULA). */
    private const PRIVATE = ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', 'fc00::/7'];

    private function __construct()
    {
    }

    /** Адрес сокета: кто на самом деле соединился с сервером. */
    public static function address(HttpRequest $request): string
    {
        $ip = (string) ($request->getServerParam('remote_addr') ?? '');

        return preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $m) === 1 ? $m[1] : $ip;
    }

    /** Адрес клиента: из `X-Forwarded-For` за доверенным прокси, иначе сокет. */
    public static function client(HttpRequest $request): string
    {
        $peer = self::address($request);
        if (!self::isTrusted($peer)) {
            return $peer;
        }

        $chain = array_map('trim', explode(',', (string) ($request->getHeader('x-forwarded-for') ?? '')));
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            $ip = $chain[$i];
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                break;
            }
            if (!self::isTrusted($ip)) {
                return $ip;
            }
            $peer = $ip;
        }

        return $peer;
    }

    public static function isLoopback(string $ip): bool
    {
        return $ip === '::1' || str_starts_with($ip, '127.');
    }

    private static function isTrusted(string $ip): bool
    {
        if (self::isLoopback($ip)) {
            return true;
        }
        foreach (self::trusted() as $range) {
            if (self::inRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] */
    private static function trusted(): array
    {
        $ranges = [];
        foreach (array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', 'private')))) as $item) {
            match (strtolower($item)) {
                'none' => null,
                'private' => array_push($ranges, ...self::PRIVATE),
                default => $ranges[] = $item,
            };
        }

        return $ranges;
    }

    private static function inRange(string $ip, string $range): bool
    {
        [$net, $bits] = str_contains($range, '/') ? explode('/', $range, 2) : [$range, null];
        $address = @inet_pton($ip);
        $network = @inet_pton($net);
        if ($address === false || $network === false || strlen($address) !== strlen($network)) {
            return false;
        }

        $bits = $bits === null ? strlen($address) * 8 : (int) $bits;
        $bytes = intdiv($bits, 8);
        if (strncmp($address, $network, $bytes) !== 0) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($address[$bytes]) & $mask) === (ord($network[$bytes]) & $mask);
    }
}
