<?php

declare(strict_types=1);

namespace Main\Controllers\Admin;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;
use Flytachi\Winter\Kernel\Http\Request\Annotation\PathVariable;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestJson;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestQuery;
use Flytachi\Winter\Kernel\Http\Request\Validation\Positive;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;
use Flytachi\Winter\Kernel\Http\Request\Validation\Uuid;
use Flytachi\Winter\Kernel\Http\Request\Validation\Valid;
use Flytachi\Winter\Kernel\Http\Response\ResponseEntity;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Route\Annotation\DeleteMapping;
use Flytachi\Winter\Kernel\Route\Annotation\GetMapping;
use Flytachi\Winter\Kernel\Route\Annotation\PostMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Enum\LinkPurge;
use Main\Request\PageRequest;
use Main\Request\ShareLinkRequest;
use Main\Service\ShareLinkService;
use Main\Support\Slug;

/**
 * Временные ссылки (docs/plan.md §4.3).
 *
 * TODO: закрыть AdminJwtMiddleware (docs/plan.md §8.8).
 */
/**
 * Префикс на уровне бакета, а не «/links», намеренно: выпуск ссылки висит на
 * файле, а управление — на коллекции ссылок. Если и то, и другое положить под
 * /links, получится один и тот же путь с разными именами переменной
 * (`links/{slug}` против `links/{id}` и `links/revoke-all`) — роутер такие
 * маршруты путает между собой.
 */
#[RequestMapping('admin/buckets/{bucketId}')]
final class ShareLinkController extends Controller
{
    #[Autowired]
    private ShareLinkService $service;

    /**
     * Выпуск ссылки на файл. Возвращается один раз: подпись в базе не хранится,
     * повторить её нечем.
     */
    #[PostMapping('files/{slug}/link')]
    public function issue(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable, Size(min: Slug::LENGTH, max: Slug::LENGTH)] string $slug,
        #[RequestJson, Valid] ShareLinkRequest $request,
        HttpRequest $http,
    ): ResponseEntity {
        return ResponseEntity::created(
            $this->service->issue($bucketId, $slug, $request, $this->baseUrl($http)),
        );
    }

    /** Ссылки этого файла — то, что показывает его карточка в панели. */
    #[GetMapping('files/{slug}/links')]
    public function ofFile(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable, Size(min: Slug::LENGTH, max: Slug::LENGTH)] string $slug,
        HttpRequest $http,
    ): ResponseEntity {
        return ResponseEntity::ok($this->service->forFile($bucketId, $slug, $this->baseUrl($http)));
    }

    /** Только ссылки со строкой: у остальных состояния нет. */
    #[GetMapping('links')]
    public function list(
        #[PathVariable, Uuid] string $bucketId,
        #[RequestQuery, Valid] PageRequest $request,
        HttpRequest $http,
    ): ResponseEntity {
        return ResponseEntity::ok(
            $this->service->getAll($bucketId, $request, $this->baseUrl($http)),
        );
    }

    /**
     * Уборка мёртвых строк. Состояние — сегментом, а не телом: DELETE с телом
     * поддерживают не все клиенты, а фильтр здесь закрытый.
     */
    #[DeleteMapping('links/purge/{state}')]
    public function purge(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable] LinkPurge $state,
    ): ResponseEntity {
        return ResponseEntity::ok(['removed' => $this->service->purge($bucketId, $state)]);
    }

    #[DeleteMapping('links/{id}')]
    public function revoke(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable, Positive] int $id,
    ): ResponseEntity {
        $this->service->revoke($bucketId, $id);
        return ResponseEntity::noContent();
    }

    /**
     * Массовый отзыв: гасит и те ссылки, которых в базе нет.
     *
     * DELETE на коллекции, а не POST links/revoke-all: статический сегмент на
     * той же глубине, что и `links/{id}`, роутер сопоставляет вторым — путь
     * уходит в маршрут с переменной и возвращает 405.
     */
    #[DeleteMapping('links')]
    public function revokeAll(
        #[PathVariable, Uuid] string $bucketId,
    ): ResponseEntity {
        return ResponseEntity::ok(['epoch' => $this->service->revokeAll($bucketId)]);
    }

    private function baseUrl(HttpRequest $http): string
    {
        return (string) env('PUBLIC_BASE_URL', $http->getBaseUrl());
    }
}
