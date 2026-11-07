<?php

declare(strict_types=1);

namespace Main\Support;

/**
 * Время в ответах API — всегда RFC 3339: `2026-11-08T17:10:44+00:00`.
 *
 * Без этого форматов было три: только что созданный объект нёс строку из `date()`
 * (`2026-11-08 17:10:44 +00:00`), а прочитанный из базы — то, что вернул PostgreSQL
 * (`2026-11-08 17:10:44+00`, с долями секунды `…44.59+00`). Клиенту приходилось
 * разбирать все три; теперь любой из них приводится к одному при выдаче.
 */
final class Time
{
    private function __construct()
    {
    }

    public static function iso(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($value)->format(\DATE_ATOM);
        } catch (\Exception) {
            return $value; // не время — отдать как есть, а не терять значение
        }
    }

    public static function isoAt(int $timestamp): string
    {
        return date(\DATE_ATOM, $timestamp);
    }
}
