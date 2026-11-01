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
use Main\Enum\TokenAccess;
use Main\Enum\TokenStatus;
use Main\Entities\AccessToken;
use Main\Http\BucketContext;
use Main\Services\AccessTokenLookup;
use Main\Services\TrafficMeter;
use Main\Support\TokenGenerator;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class AccessTokenMiddleware extends Middleware
{
    private const int TOUCH_INTERVAL = 300;

    #[Autowired]
    private AccessTokenLookup $tokens;

    #[Autowired]
    private BucketContext $context;

    #[Autowired]
    private TrafficMeter $traffic;

    public function before(HttpRequest $request, HttpResponse $response): void
    {
        $token = Header::getBearerToken();
        if ($token === null || $token === '') {
            MiddlewareException::throw('Access token required', HttpCode::UNAUTHORIZED);
        }

        $hash  = TokenGenerator::hash($token);
        $model = $this->tokens->find($hash);

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

        $this->touch($model);
        // Приватная выдача `/p/{slug}` предъявляет токен и отдаёт файл, поэтому попадёт
        // и сюда, и в счётчик раздачи. Это не двойной учёт: числа отвечают на разные
        // вопросы и складывать их в одно не предполагается.
        $this->traffic->apiHit($model->bucket_id);
        $this->context->setToken($model);
    }

    protected function required(): TokenAccess
    {
        return TokenAccess::BASIC;
    }

    /** Отмечает, что токеном воспользовались, — не чаще раза в {@see TOUCH_INTERVAL}. */
    private function touch(AccessToken $model): void
    {
        $lastUsedAt = $model->last_used_at;
        if ($lastUsedAt !== null && (time() - (int) strtotime($lastUsedAt)) < self::TOUCH_INTERVAL) {
            return;
        }

        $this->tokens->touch($model, date('Y-m-d H:i:s P'));
    }
}
