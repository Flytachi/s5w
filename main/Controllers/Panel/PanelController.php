<?php

declare(strict_types=1);

namespace Main\Controllers\Panel;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;
use Flytachi\Winter\Kernel\Http\Request\Annotation\PathVariable;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestQuery;
use Flytachi\Winter\Kernel\Http\Request\Validation\Uuid;
use Flytachi\Winter\Kernel\Http\Request\Validation\Valid;
use Flytachi\Winter\Kernel\Http\Response\ResponseEntity;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Route\Annotation\GetMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Controllers\Middlewares\AdminAuthMiddleware;
use Main\Controllers\Requests\BucketListRequest;
use Main\Controllers\Requests\LinkListRequest;
use Main\Controllers\Requests\PanelListRequest;
use Main\Controllers\Requests\TokenListRequest;
use Main\Controllers\Requests\TrafficRangeRequest;
use Main\Dto\CountedPageRes;
use Main\Dto\FilePageRes;
use Main\Http\ViewerTimezone;
use Main\Services\AccessTokenService;
use Main\Services\BucketService;
use Main\Services\FileService;
use Main\Services\FolderService;
use Main\Services\PanelService;
use Main\Services\ShareLinkService;

/**
 * Чтение для панели: одна страница — один запрос, данные уже собраны (бакет со
 * статистикой, папка со счётчиком файлов, ссылка с файлом и состоянием). Подписей почти
 * нет — ключи и числа, переводит и форматирует фронт; исключение — `accessLabel` токена. Изменения идут через ресурсный
 * API `/admin/buckets/…`.
 */
#[AdminAuthMiddleware]
#[RequestMapping('admin/panel')]
final class PanelController extends Controller
{
    #[Autowired]
    private PanelService $panel;

    #[Autowired]
    private BucketService $buckets;

    #[Autowired]
    private FolderService $folders;

    #[Autowired]
    private FileService $files;

    #[Autowired]
    private AccessTokenService $tokens;

    #[Autowired]
    private ShareLinkService $links;

    /**
     * What the panel cannot know by itself: where the client API and delivery live.
     * `configured` is false when PUBLIC_BASE_URL is unset and the address is a guess
     * from the request — the panel says so.
     */
    #[GetMapping('info')]
    public function info(HttpRequest $http): ResponseEntity
    {
        return ResponseEntity::ok([
            'baseUrl' => $this->baseUrl($http),
            'configured' => (string) env('PUBLIC_BASE_URL', '') !== '',
        ]);
    }

    #[GetMapping('dashboard')]
    public function dashboard(): ResponseEntity
    {
        return ResponseEntity::ok($this->panel->dashboard(ViewerTimezone::resolve()));
    }

    #[GetMapping('buckets')]
    public function buckets(#[RequestQuery, Valid] BucketListRequest $request): ResponseEntity
    {
        $page = $this->buckets->cardPage($request);

        return ResponseEntity::ok(new CountedPageRes($page->meta, $page->data, $this->buckets->counts()));
    }

    #[GetMapping('buckets/{id}')]
    public function overview(#[PathVariable, Uuid] string $id): ResponseEntity
    {
        return ResponseEntity::ok($this->panel->overview($id, ViewerTimezone::resolve()));
    }

    #[GetMapping('buckets/{id}/stats')]
    public function stats(
        #[PathVariable, Uuid] string $id,
        #[RequestQuery, Valid] TrafficRangeRequest $request,
    ): ResponseEntity {
        $tz = ViewerTimezone::resolve();
        [$from, $to] = $request->resolve($tz);

        return ResponseEntity::ok($this->panel->traffic($id, $from, $to, $tz));
    }

    #[GetMapping('buckets/{id}/files')]
    public function files(
        #[PathVariable, Uuid] string $id,
        #[RequestQuery, Valid] PanelListRequest $request,
        HttpRequest $http,
    ): ResponseEntity {
        $card = $this->buckets->card($id);
        $page = $this->files->getAll($id, $request->toFileList(), $this->baseUrl($http));

        return ResponseEntity::ok(new FilePageRes($page->meta, $page->data, $this->folders->cards($id), $card->files));
    }

    #[GetMapping('buckets/{id}/folders')]
    public function folders(#[PathVariable, Uuid] string $id): ResponseEntity
    {
        $this->buckets->get($id);

        return ResponseEntity::ok($this->folders->cards($id));
    }

    #[GetMapping('buckets/{id}/tokens')]
    public function tokens(
        #[PathVariable, Uuid] string $id,
        #[RequestQuery, Valid] TokenListRequest $request,
    ): ResponseEntity {
        $this->buckets->get($id);
        $page = $this->tokens->panelPage($id, $request);

        return ResponseEntity::ok(new CountedPageRes($page->meta, $page->data, $this->tokens->counts($id)));
    }

    #[GetMapping('buckets/{id}/links')]
    public function links(
        #[PathVariable, Uuid] string $id,
        #[RequestQuery, Valid] LinkListRequest $request,
        HttpRequest $http,
    ): ResponseEntity {
        $this->buckets->get($id);
        $page = $this->links->cardPage($id, $request, $this->baseUrl($http));

        return ResponseEntity::ok(new CountedPageRes($page->meta, $page->data, $this->links->counts($id)));
    }

    private function baseUrl(HttpRequest $http): string
    {
        $toEnv = (string) env('PUBLIC_BASE_URL', '');

        return empty($toEnv) ? $http->getBaseUrl() : $toEnv;
    }
}
