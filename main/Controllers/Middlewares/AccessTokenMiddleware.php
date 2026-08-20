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
use Main\Cacheable\TokenCache;
use Main\Entity\AccessToken;
use Main\Http\BucketContext;
use Main\Service\TrafficMeter;
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

    #[Autowired]
    private TokenCache $cache;

    #[Autowired]
    private TrafficMeter $traffic;

    public function before(HttpRequest $request, HttpResponse $response): void
    {
        $token = Header::getBearerToken();
        if ($token === null || $token === '') {
            MiddlewareException::throw('Access token required', HttpCode::UNAUTHORIZED);
        }

        $hash  = TokenGenerator::hash($token);
        $model = $this->cache->get($hash);
        if ($model === null) {
            $model = $this->repo->findBy(Qb::eq('hash', $hash));
            if ($model !== null) {
                $this->cache->put($model);
            }
        }

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

    /**
     * Отмечает, что токеном воспользовались, — не чаще раза в {@see TOUCH_INTERVAL}.
     *
     * Отметку кладём и в кэш, а не только в базу. Иначе выходит ловушка: закэшированная
     * строка держит старое `last_used_at`, каждый следующий запрос в пределах срока
     * жизни кэша снова считает её просроченной и снова пишет — то есть запись на каждый
     * запрос в одну и ту же строку. Замерено на pgbench: 16 писателей в одну строку —
     * 1 390 tps против 10 216 по разным строкам, разница в 7.3 раза плюс мёртвые версии
     * строк и работа для autovacuum.
     */
    private function touch(AccessToken $model): void
    {
        $lastUsedAt = $model->last_used_at;
        if ($lastUsedAt !== null && (time() - (int) strtotime($lastUsedAt)) < self::TOUCH_INTERVAL) {
            return;
        }

        $now = date('Y-m-d H:i:s P');
        $this->repo->update(['last_used_at' => $now], Qb::eq('id', $model->id));

        $model->last_used_at = $now;
        $this->cache->put($model);
    }
}
