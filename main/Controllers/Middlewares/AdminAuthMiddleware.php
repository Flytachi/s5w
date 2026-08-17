<?php

declare(strict_types=1);

namespace Main\Controllers\Middlewares;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;
use Flytachi\Winter\Kernel\Http\Contracts\HttpResponse;
use Flytachi\Winter\Kernel\Http\Header;
use Flytachi\Winter\Kernel\Http\Middleware\MiddlewareException;
use Flytachi\Winter\Kernel\Http\Stereotype\Middleware;
use Main\Http\AdminCookie;
use Main\Service\AdminAuthService;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class AdminAuthMiddleware extends Middleware
{
    #[Autowired]
    private AdminAuthService $auth;

    public function before(HttpRequest $request, HttpResponse $response): void
    {
        $token = Header::getBearerToken() ?? AdminCookie::read($request);
        if ($this->auth->verify($token)) {
            return;
        }

        $this->reject($request);
    }

    protected function reject(HttpRequest $request): void
    {
        MiddlewareException::throw('Authorization required', HttpCode::UNAUTHORIZED);
    }
}
