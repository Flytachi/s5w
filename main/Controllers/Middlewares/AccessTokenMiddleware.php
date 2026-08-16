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
use Main\Enum\TokenStatus;
use Main\Http\BucketContext;
use Main\Repository\AccessTokenRepository;
use Main\Support\TokenGenerator;

/**
 * Аутентификация арендатора по Bearer-токену бакета.
 *
 * Токен в базе не хранится — ищем по его sha256, поэтому поиск идёт по
 * уникальному индексу, а утечка дампа не даёт доступа.
 *
 * TODO: кэшировать пару «хеш → бакет» (winter-cache), иначе каждая отдача по
 * /p стоит запроса к базе. Тогда же сбрасывать запись при ротации и отзыве.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class AccessTokenMiddleware extends Middleware
{
    /** Как часто обновляем last_used_at: чаще — лишняя запись на каждый запрос. */
    private const int TOUCH_INTERVAL = 300;

    #[Autowired]
    private AccessTokenRepository $repo;

    #[Autowired]
    private BucketContext $context;

    public function before(HttpRequest $request, HttpResponse $response): void
    {
        $token = Header::getBearerToken();
        if ($token === null || $token === '') {
            MiddlewareException::throw('Token required', HttpCode::UNAUTHORIZED);
        }

        $model = $this->repo->findBy(Qb::eq('hash', TokenGenerator::hash($token)));
        if ($model === null) {
            MiddlewareException::throw('Invalid token', HttpCode::UNAUTHORIZED);
        }

        // Выключенный или просроченный токен — это 403, а не 401: повторная
        // аутентификация тем же секретом ничего не изменит.
        if ($model->status !== TokenStatus::ACTIVE->value) {
            MiddlewareException::throw('Token is inactive', HttpCode::FORBIDDEN);
        }
        if ($model->expires_at !== null && strtotime($model->expires_at) <= time()) {
            MiddlewareException::throw('Token expired', HttpCode::FORBIDDEN);
        }

        $this->touch($model->id, $model->last_used_at);
        $this->context->set($model->bucket_id);
    }

    private function touch(int $id, ?string $lastUsedAt): void
    {
        if ($lastUsedAt !== null && (time() - (int) strtotime($lastUsedAt)) < self::TOUCH_INTERVAL) {
            return;
        }

        $this->repo->update(['last_used_at' => date('Y-m-d H:i:s P')], Qb::eq('id', $id));
    }
}
