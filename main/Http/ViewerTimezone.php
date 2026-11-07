<?php

declare(strict_types=1);

namespace Main\Http;

use Flytachi\Winter\Kernel\Http\Cookie\Cookie;
use Flytachi\Winter\Kernel\Http\Header;

/**
 * Часовой пояс, в котором пользователю показываются даты.
 *
 * Час в базе записан в UTC, а «20 августа» в UTC и в Ташкенте — разные сутки, так
 * что пояс решает, как лягут столбики и что написано под каждой датой в панели.
 * Берём первое известное имя пояса: заголовок `Timezone` (его панель шлёт с каждым
 * запросом) или `X-Timezone`, затем кука `tz`, если клиент её поставил, затем
 * `TIME_ZONE` окружения; неизвестное имя пропускается, в конце — UTC.
 */
final class ViewerTimezone
{
    private function __construct()
    {
    }

    public static function resolve(): \DateTimeZone
    {
        $candidates = [
            Header::get('Timezone'),
            Header::get('X-Timezone'),
            Cookie::get('tz'),
            (string) env('TIME_ZONE', 'UTC'),
        ];

        foreach ($candidates as $name) {
            if (is_string($name) && $name !== '' && in_array($name, timezone_identifiers_list(), true)) {
                return new \DateTimeZone($name);
            }
        }

        return new \DateTimeZone('UTC');
    }
}
