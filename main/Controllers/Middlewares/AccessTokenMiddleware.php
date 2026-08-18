<?php

declare(strict_types=1);

namespace Main\Controllers\Middlewares;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\Cdo\Qb;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;
use Flytachi\Winter\Kernel\Http\Contracts\HttpResponse;
use Flytachi\Winter\Kernel\Http\Header;
use Flytachi\Winter\Kernel\Http\Middleware\MiddlewareException;
use Flytachi\Winter\Kernel\Http\Stereotype\Middleware;
use Main\Enum\TokenAccess;
use Main\Enum\TokenStatus;
use Main\Http\BucketContext;
use Main\Repository\AccessTokenRepository;
use Main\Support\TokenGenerator;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class AccessTokenMiddleware extends Middleware
{
    private const int TOUCH_INTERVAL = 300;

    #[Autowired]
    private AccessTokenRepository $repo;

    #[Autowired]
    private BucketContext $context;

    public function before(HttpRequest $request, HttpResponse $response): void
    {
        $token = Header::getBearerToken();
        if ($token === null || $token === '') {
            MiddlewareException::throw('Access token required', HttpCode::UNAUTHORIZED);
        }

        $model = $this->repo->findBy(Qb::eq('hash', TokenGenerator::hash($token)));
        if ($model === null) {
            MiddlewareException::throw('Access token is invalid', HttpCode::UNAUTHORIZED);
        }

        if ($model->status !== TokenStatus::ACTIVE->value) {
            MiddlewareException::throw('Access token is disabled', HttpCode::FORBIDDEN);
        }
        if ($model->expires_at !== null && strtotime($model->expires_at) <= time()) {
            MiddlewareException::throw('Access token has expired', HttpCode::FORBIDDEN);
        }

        $required = $this->required();
        if (!TokenAccess::from($model->access)->allows($required)) {
            MiddlewareException::throw(
                sprintf('This endpoint requires a token with "%s" access', strtolower($required->name)),
                HttpCode::FORBIDDEN,
            );
        }

        $this->touch($model->id, $model->last_used_at);
        $this->context->setToken($model);
    }

    protected function required(): TokenAccess
    {
        return TokenAccess::BASIC;
    }

    private function touch(int $id, ?string $lastUsedAt): void
    {
        if ($lastUsedAt !== null && (time() - (int) strtotime($lastUsedAt)) < self::TOUCH_INTERVAL) {
            return;
        }

        $this->repo->update(['last_used_at' => date('Y-m-d H:i:s P')], Qb::eq('id', $id));
    }
}
