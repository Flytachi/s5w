<?php

declare(strict_types=1);

namespace Main\Support;

final class Db
{
    public static function isUniqueViolation(\Throwable $e): bool
    {
        return self::pdoException($e) !== null;
    }

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
