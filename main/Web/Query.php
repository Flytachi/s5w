<?php

declare(strict_types=1);

namespace Main\Web;

final class Query
{
    public static function url(string $path, array $params): string
    {
        $params = array_filter(
            $params,
            static fn($value) => $value !== null && $value !== '' && $value !== 1,
        );

        return $params === [] ? $path : $path . '?' . http_build_query($params);
    }
}
