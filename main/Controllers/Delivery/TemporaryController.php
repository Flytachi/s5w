<?php

declare(strict_types=1);

namespace Main\Controllers\Delivery;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Request\Annotation\PathVariable;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Route\Annotation\GetMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Http\BlobResponse;
use Main\Service\DeliveryService;

/**
 * Отдача по временной ссылке: /t/{token}.
 *
 * Авторизации нет — доступ даёт сама подпись. Никаких query-параметров:
 * disposition зашит в подпись, а весь токен и есть секрет, который не должен
 * оседать в логах отдельным полем.
 */
#[RequestMapping('t')]
final class TemporaryController extends Controller
{
    #[Autowired]
    private DeliveryService $service;

    #[GetMapping('{token}')]
    public function download(
        // 46 символов без jti, 56 с ним — с запасом на будущие версии формата.
        #[PathVariable, Size(min: 40, max: 128)] string $token,
    ): BlobResponse {
        return $this->service->temporary($token);
    }
}
