<?php

declare(strict_types=1);

namespace Main\Support;

final class Db
{
    /**
     * UNIQUE-нарушение Postgres (SQLSTATE 23505).
     *
     * Идём по цепочке previous: репозиторий заворачивает CDOException, тот —
     * PDOException. Смотрим на SQLSTATE, а не на текст сообщения — текст
     * зависит от версии сервера и локали.
     */
    public static function isUniqueViolation(\Throwable $e): bool
    {
        return self::pdoException($e) !== null;
    }

    /**
     * Имя нарушенного UNIQUE-индекса — когда на таблице их несколько и реакция
     * зависит от того, какой именно сработал.
     *
     * Postgres кладёт его в текст ошибки: `duplicate key value violates unique
     * constraint "files_slug_udx"`. Текст — единственный источник: SQLSTATE
     * одинаков для всех уникальных индексов таблицы.
     */
    public static function uniqueConstraint(\Throwable $e): ?string
    {
        $pdo = self::pdoException($e);
        if ($pdo === null) {
            return null;
        }
        return preg_match('/unique constraint "([^"]+)"/', $pdo->getMessage(), $m) === 1
            ? $m[1]
            : null;
    }

    private static function pdoException(\Throwable $e): ?\PDOException
    {
        for ($cur = $e; $cur !== null; $cur = $cur->getPrevious()) {
            if ($cur instanceof \PDOException && (string) $cur->getCode() === '23505') {
                return $cur;
            }
        }
        return null;
    }
}
