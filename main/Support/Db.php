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
        for ($cur = $e; $cur !== null; $cur = $cur->getPrevious()) {
            if ($cur instanceof \PDOException && (string) $cur->getCode() === '23505') {
                return true;
            }
        }
        return false;
    }
}
