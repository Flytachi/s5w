<?php

declare(strict_types=1);

namespace Main\Controllers\Web;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;
use Flytachi\Winter\Kernel\Http\Cookie\Cookie;
use Flytachi\Winter\Kernel\Http\Header;
use Flytachi\Winter\Kernel\Http\Request\Annotation\PathVariable;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestQuery;
use Flytachi\Winter\Kernel\Http\Request\Validation\Uuid;
use Flytachi\Winter\Kernel\Http\Request\Validation\Valid;
use Flytachi\Winter\Kernel\Http\Response\ResponseView;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Route\Annotation\GetMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Controllers\Middlewares\AdminPageMiddleware;
use Main\Dto\FileRes;
use Main\Request\BucketListRequest;
use Main\Request\LinkListRequest;
use Main\Request\PanelListRequest;
use Main\Request\TokenListRequest;
use Main\Request\TrafficRangeRequest;
use Main\Service\AccessTokenService;
use Main\Service\BucketService;
use Main\Service\BucketTrafficService;
use Main\Service\FileService;
use Main\Service\FolderService;
use Main\Service\ShareLinkService;
use Main\Service\UploadService;
use Main\Web\BucketView;
use Main\Web\FileView;
use Main\Web\FolderView;
use Main\Web\LinkView;
use Main\Web\Query;

#[AdminPageMiddleware]
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

    #[Autowired]
    private BucketTrafficService $traffic;

    #[Autowired]
    private UploadService $uploads;

    private const int WINDOW = 30;

    private const int TOP = 6;

    #[GetMapping]
    public function dashboard(): ResponseView
    {
        $tz = $this->timezone();
        $today = new \DateTimeImmutable('now', $tz);
        $from = $today->modify('-' . (self::WINDOW - 1) . ' days')->format('Y-m-d');
        $to = $today->format('Y-m-d');

        $series = $this->traffic->daily(null, $from, $to, $tz->getName());
        $buckets = $this->buckets->all();
        $stats = $this->buckets->stats(array_column($buckets, 'id'));

        $cards = array_map(
            static fn($bucket) => BucketView::from($bucket, $stats[$bucket->id]),
            $buckets,
        );
        usort($cards, static fn(BucketView $a, BucketView $b) => $b->percent() <=> $a->percent());

        $withQuota = array_values(array_filter($cards, static fn(BucketView $card) => $card->quota > 0));

        $served = $this->traffic->topBuckets($from, $to, $tz->getName());
        $loud = array_column($served, 'bucket_id');
        $quiet = array_values(array_filter(
            array_map(static fn(BucketView $card) => in_array($card->id, $loud, true) ? null : $card->name, $cards),
        ));

        return $this->page('dashboard', 'Обзор', 'dashboard', [
            'subtitle' => 'всё хранилище целиком',
            'window' => self::WINDOW,
            'from' => $from,
            'to' => $to,
            'series' => $series,
            'totals' => $this->traffic->totals($series),
            'top' => array_slice($served, 0, self::TOP),
            'topTotal' => count($served),
            'quiet' => $quiet,
            'fill' => array_slice($withQuota, 0, self::TOP),
            'fillTotal' => count($withQuota),
            'counts' => $this->buckets->counts(),
            'blobs' => $this->buckets->blobCounts(),
            'tokenCounts' => $this->tokens->counts(),
            'linkCounts' => $this->links->counts(),
            'uploadCounts' => $this->uploads->counts(),
        ]);
    }

    #[GetMapping('buckets')]
    public function buckets(
        #[RequestQuery, Valid] BucketListRequest $request,
    ): ResponseView {
        $page = $this->buckets->panelPage($request);

        return $this->page('buckets', 'Бакеты', 'buckets', [
            'subtitle' => 'пространства с отдельной квотой и своими блобами',
            'modals' => true,
            'counts' => $this->buckets->counts(),
            'page' => $page,
            'meta' => $page->meta,
            'query' => $request,
            'pageUrl' => static fn(int $number) => $request->url(['page' => $number]),
        ]);
    }

    #[GetMapping('buckets/{id}')]
    public function overview(#[PathVariable, Uuid] string $id): ResponseView
    {
        $tz = $this->timezone();
        $month = (new \DateTimeImmutable('now', $tz))->format('Y-m-01');
        $today = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
        $series = $this->traffic->daily($id, $month, $today, $tz->getName());

        return $this->bucketPage($id, 'bucket-overview', 'overview', 'сводка по бакету', [
            'series' => $series,
            'totals' => $this->traffic->totals($series),
            'folders' => $this->folderViews($id),
            'usage' => $this->buckets->usage($id),
            'placement' => $this->buckets->placement($id),
            'folderCounts' => $this->buckets->folderCounts($id),
            'tokenCounts' => $this->tokens->counts($id),
            'linkCounts' => $this->links->counts($id),
        ], withStats: true);
    }

    #[GetMapping('buckets/{id}/stats')]
    public function stats(
        #[PathVariable, Uuid] string $id,
        #[RequestQuery, Valid] TrafficRangeRequest $request,
    ): ResponseView {
        $tz = $this->timezone();
        [$from, $to] = $request->resolve($tz);
        $series = $this->traffic->daily($id, $from, $to, $tz->getName());

        return $this->bucketPage($id, 'bucket-stats', 'stats', 'расход бакета по дням', [
            'series' => $series,
            'totals' => $this->traffic->totals($series),
            'from' => $from,
            'to' => $to,
            'timezone' => $tz->getName(),
        ]);
    }

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
        ], withStats: true);
    }

    #[GetMapping('buckets/{id}/tokens')]
    public function tokens(
        #[PathVariable, Uuid] string $id,
        #[RequestQuery, Valid] TokenListRequest $request,
    ): ResponseView {
        $page = $this->tokens->panelPage($id, $request);

        return $this->bucketPage($id, 'bucket-tokens', 'tokens', 'ключи клиента к бакету', [
            'meta' => $page->meta,
            'tokens' => $page->data,
            'counts' => $this->tokens->counts($id),
            'query' => $request,
            'pageUrl' => static fn(int $number) => $request->url($id, ['page' => $number]),
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
        bool $withStats = false,
    ): ResponseView {
        $bucket = $this->buckets->get($id);
        $stats = $withStats
            ? $this->buckets->stats([$id])[$id]
            : ['files' => 0, 'blobs' => 0, 'folders' => 0];
        $current = BucketView::from($bucket, $stats);

        return $this->page($resource, $current->name, $nav, $data + [
            'subtitle' => '',
            'bucket' => $current,
            // На странице статистики действий нет — модалки ей не нужны.
            'modals' => $nav !== 'stats',
        ]);
    }

    /**
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
            'timezone' => $this->timezone()->getName(),
            'buckets' => $this->switcherBuckets(),
        ]);
    }

    /**
     * Часовой пояс, в котором пользователю показываются даты.
     *
     * Час в базе записан в UTC, а «20 августа» в UTC и в Ташкенте — разные сутки, так
     * что пояс решает, как лягут столбики и что написано под каждой датой в панели.
     * Берём его в трёх попытках: заголовок `Timezone`, который панель шлёт своими
     * запросами; кука `tz`, которую ставит браузер при первой отрисовке страницы
     * (обычная навигация заголовков не шлёт); и наконец `TIME_ZONE` окружения.
     */
    private function timezone(): \DateTimeZone
    {
        $candidates = [
            Header::get('Timezone'),
            Header::get('X-Timezone'),
            Cookie::get('tz'),
            (string) env('TIME_ZONE', 'UTC'),
        ];

        foreach ($candidates as $name) {
            if (is_string($name) && $name !== '' && in_array($name, timezone_identifiers_list(), true)) {
                return new \DateTimeZone($name);
            }
        }

        return new \DateTimeZone('UTC');
    }

    private function baseUrl(HttpRequest $http): string
    {
        $toEnv = (string) env('PUBLIC_BASE_URL', '');
        return empty($toEnv) ? $http->getBaseUrl() : $toEnv;    }
}
