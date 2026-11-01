<?php

declare(strict_types=1);

namespace Main\Controllers\Middlewares;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;
use Flytachi\Winter\Kernel\Http\Middleware\MiddlewareException;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class AdminPageMiddleware extends AdminAuthMiddleware
{
    protected function reject(HttpRequest $request): void
    {
        $next = $request->getUri();

        throw (new MiddlewareException('Authentication required', HttpCode::FOUND))
            ->withHeader('Location', '/admin/ui/login?next=' . rawurlencode($next));
    }
}
