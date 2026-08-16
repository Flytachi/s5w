<?php

declare(strict_types=1);

namespace Main\Controllers\Web;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;
use Flytachi\Winter\Kernel\Http\Request\Annotation\PathVariable;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestQuery;
use Flytachi\Winter\Kernel\Http\Request\Validation\Uuid;
use Flytachi\Winter\Kernel\Http\Request\Validation\Valid;
use Flytachi\Winter\Kernel\Http\Response\ResponseView;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Route\Annotation\GetMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Dto\FileRes;
use Main\Request\BucketListRequest;
use Main\Request\LinkListRequest;
use Main\Request\PanelListRequest;
use Main\Service\AccessTokenService;
use Main\Service\BucketService;
use Main\Service\FileService;
use Main\Service\FolderService;
use Main\Service\ShareLinkService;
use Main\Web\BucketView;
use Main\Web\FileView;
use Main\Web\FolderView;
use Main\Web\LinkView;
use Main\Web\Query;
use Main\Web\MockData;

/**
 * Веб-админка под /admin/ui — отдельно от API на /admin/buckets.
 *
 * Навигация составная: сверху общее, ниже выбранный бакет со своими файлами,
 * папками, токенами и ссылками. Токен и ссылка вне бакета не существуют.
 *
 * Все разделы работают с базой; на выдумке остался только обзор — там сводка
 * по всему хранилищу, считать которую пока некому.
 *
 * TODO: закрыть AdminJwtMiddleware вместе с API (docs/plan.md §8.8).
 */
#[RequestMapping('admin/ui')]
final class AdminWebController extends Controller
{
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

    #[GetMapping]
    public function dashboard(): ResponseView
    {
        $buckets = $this->buckets->all();
        $stats = $this->buckets->stats(array_column($buckets, 'id'));

        return $this->page('dashboard', 'Обзор', 'dashboard', [
            'subtitle' => 'всё хранилище целиком',
            'mocked' => true,
            'overview' => MockData::overview(),
            'events' => MockData::events(),
            'cards' => array_map(
                static fn($bucket) => BucketView::from($bucket, $stats[$bucket->id]),
                $buckets,
            ),
        ]);
    }

    #[GetMapping('buckets')]
    public function buckets(
        #[RequestQuery, Valid] BucketListRequest $request,
    ): ResponseView {
        $page = $this->buckets->panelPage($request);

        return $this->page('buckets', 'Бакеты', 'buckets', [
            'subtitle' => 'пространства с отдельной квотой и своими блобами',
            'page' => $page,
            'meta' => $page->meta,
            'query' => $request,
            'pageUrl' => static fn(int $number) => $request->url(['page' => $number]),
        ]);
    }

    /** Обзор бакета — первое, что видно при заходе: цифры и настройки. */
    #[GetMapping('buckets/{id}')]
    public function overview(#[PathVariable, Uuid] string $id): ResponseView
    {
        return $this->bucketPage($id, 'bucket-overview', 'overview', 'сводка по бакету', [
            'folders' => $this->folderViews($id),
            'linkCounts' => $this->links->counts($id),
            'tokenCount' => $this->tokens->getAll($id, (new PanelListRequest(limit: 5))->toPage())->meta->total,
        ]);
    }

    /**
     * Файловый менеджер: папки и файлы в одном окне.
     *
     * Папка выбирается через тот же параметр, что и фильтр списка, — адрес
     * остаётся честным, а постраничность считается сервером по выбранной папке.
     */
    #[GetMapping('buckets/{id}/files')]
    public function files(
        #[PathVariable, Uuid] string $id,
        #[RequestQuery, Valid] PanelListRequest $request,
        HttpRequest $http,
    ): ResponseView {
        $page = $this->files->getAll($id, $request->toFileList(), $this->baseUrl($http));

        return $this->bucketPage($id, 'bucket-files', 'files', 'папки и файлы', [
            'meta' => $page->meta,
            'items' => array_map(static fn(FileRes $file) => FileView::from($file), $page->data),
            'query' => $request,
            'folders' => $this->folderViews($id),
            'pageUrl' => static fn(int $number) => Query::url(
                '/admin/ui/buckets/' . $id . '/files',
                $request->params($number),
            ),
        ]);
    }

    #[GetMapping('buckets/{id}/tokens')]
    public function tokens(
        #[PathVariable, Uuid] string $id,
        #[RequestQuery, Valid] PanelListRequest $request,
    ): ResponseView {
        $page = $this->tokens->getAll($id, $request->toPage());

        return $this->bucketPage($id, 'bucket-tokens', 'tokens', 'ключи клиента к каналу /p', [
            'meta' => $page->meta,
            'tokens' => $page->data,
            'pageUrl' => static fn(int $number) => Query::url(
                '/admin/ui/buckets/' . $id . '/tokens',
                $request->params($number),
            ),
        ]);
    }

    #[GetMapping('buckets/{id}/links')]
    public function links(
        #[PathVariable, Uuid] string $id,
        #[RequestQuery, Valid] LinkListRequest $request,
        HttpRequest $http,
    ): ResponseView {
        $page = $this->links->panelPage($id, $request, $this->baseUrl($http));

        return $this->bucketPage($id, 'bucket-links', 'links', 'канал /t — доступ даёт подпись', [
            'meta' => $page['meta'],
            'links' => array_map(
                static fn(array $row) => LinkView::from($row['link'], $row['file'], $row['url']),
                $page['items'],
            ),
            'counts' => $this->links->counts($id),
            'query' => $request,
            'pageUrl' => static fn(int $number) => $request->url($id, ['page' => $number]),
        ]);
    }

    /** Отдельная страница без каркаса админки. */
    #[GetMapping('login')]
    public function login(): ResponseView
    {
        return ResponseView::view('admin/login');
    }

    // ── Внутреннее ───────────────────────────────────────────────────────────

    /** @return FolderView[] */
    private function folderViews(string $bucketId): array
    {
        return array_map(
            static fn(array $row) => FolderView::from($row['folder'], $row['files']),
            $this->folders->panelList($bucketId),
        );
    }

    private function bucketPage(
        string $id,
        string $resource,
        string $nav,
        string $subtitle,
        array $data,
    ): ResponseView {
        $bucket = $this->buckets->get($id);
        $current = BucketView::from($bucket, $this->buckets->stats([$id])[$id]);

        // Шапка внутри бакета — только имя и статус: раздел виден по подсветке
        // в меню, описание есть на обзоре, а строка подписи забирала высоту у
        // файлового окна.
        return $this->page($resource, $current->name, $nav, $data + [
            'subtitle' => '',
            'bucket' => $current,
        ]);
    }

    /**
     * Список для переключателя в боковом меню — без счётчиков: там нужны
     * только имя и статус, а считать содержимое каждого бакета ради этого
     * значило бы три запроса на выпадающий список.
     *
     * @return BucketView[]
     */
    private function switcherBuckets(): array
    {
        $empty = ['files' => 0, 'blobs' => 0, 'folders' => 0];

        return array_map(
            static fn($bucket) => BucketView::from($bucket, $empty),
            $this->buckets->all(),
        );
    }

    private function page(string $resource, string $title, string $nav, array $data = []): ResponseView
    {
        return ResponseView::render('layouts/admin', 'admin/' . $resource, $data + [
            'title' => $title,
            'nav' => $nav,
            'buckets' => $this->switcherBuckets(),
        ]);
    }

    private function baseUrl(HttpRequest $http): string
    {
        return (string) env('PUBLIC_BASE_URL', $http->getBaseUrl());
    }
}
