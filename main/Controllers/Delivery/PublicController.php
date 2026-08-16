<?php

declare(strict_types=1);

namespace Main\Controllers\Delivery;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;
use Flytachi\Winter\Kernel\Http\Request\Annotation\PathVariable;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;
use Flytachi\Winter\Kernel\Http\Request\Validation\Uuid;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Route\Annotation\GetMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Http\BlobResponse;
use Main\Service\DeliveryService;
use Main\Support\Slug;

/**
 * Публичная отдача: /o/{bucket}/{slug} — без авторизации.
 *
 * Доступны только файлы с public = true, то есть корень бакета и публичные
 * папки. Пара «UUID бакета + 96-битный slug» делает перебор бессмысленным,
 * а приватный файл здесь неотличим от несуществующего — 404 в обоих случаях.
 */
#[RequestMapping('o')]
final class PublicController extends Controller
{
    #[Autowired]
    private DeliveryService $service;

    #[GetMapping('{bucketId}/{slug}')]
    public function download(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable, Size(min: Slug::LENGTH, max: Slug::LENGTH)] string $slug,
        HttpRequest $http,
    ): BlobResponse {
        return $this->service->public($bucketId, $slug, self::isDownload($http));
    }

    /** ?download=1 → отдать вложением, что бы ни говорил mime. */
    public static function isDownload(HttpRequest $http): bool
    {
        return ($http->getQueryParams()['download'] ?? null) === '1';
    }
}
