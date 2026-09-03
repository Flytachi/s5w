<?php

declare(strict_types=1);

namespace Main\Controllers\Middlewares;

use Main\Enum\TokenAccess;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class FullTokenMiddleware extends AccessTokenMiddleware
{
    protected function required(): TokenAccess
    {
        return TokenAccess::FULL;
    }
}
