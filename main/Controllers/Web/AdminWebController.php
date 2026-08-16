<?php

declare(strict_types=1);

namespace Main\Controllers\Web;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\Kernel\Exception\ClientError;
use Flytachi\Winter\Kernel\Http\Request\Annotation\PathVariable;
use Flytachi\Winter\Kernel\Http\Response\ResponseView;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Route\Annotation\GetMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Web\MockData;

/**
 * Веб-админка. Пока макет: страницы рисуются на данных из {@see MockData},
 * запросов к сервисам нет ни одного.
 *
 * Живёт под /admin/ui, чтобы не пересекаться с API на /admin/buckets.
 *
 * Навигация составная: сверху общее (обзор и список бакетов), ниже —
 * выбранный бакет со своими файлами, папками, токенами и ссылками. Токен и
 * ссылка вне бакета не существуют, поэтому и в меню их отдельно нет.
 *
 * TODO: закрыть AdminJwtMiddleware вместе с API (docs/plan.md §8.8).
 */
#[RequestMapping('admin/ui')]
final class AdminWebController extends Controller
{
    #[GetMapping]
    public function dashboard(): ResponseView
    {
        return $this->page('dashboard', 'Обзор', 'dashboard', [
            'subtitle' => 'всё хранилище целиком',
            'overview' => MockData::overview(),
            'events' => MockData::events(),
        ]);
    }

    #[GetMapping('buckets')]
    public function buckets(): ResponseView
    {
        return $this->page('buckets', 'Бакеты', 'buckets', [
            'subtitle' => 'пространства с отдельной квотой и своими блобами',
        ]);
    }

    #[GetMapping('buckets/{id}')]
    public function files(#[PathVariable] string $id): ResponseView
    {
        $bucket = $this->requireBucket($id);

        return $this->page('bucket-files', $bucket['name'], 'files', [
            'subtitle' => 'файлы и загрузка',
            'bucket' => $bucket,
            'folders' => MockData::folders($id),
            'files' => MockData::files($id),
        ]);
    }

    #[GetMapping('buckets/{id}/folders')]
    public function folders(#[PathVariable] string $id): ResponseView
    {
        $bucket = $this->requireBucket($id);

        return $this->page('bucket-folders', $bucket['name'], 'folders', [
            'subtitle' => 'видимость, срок хранения и кэш',
            'bucket' => $bucket,
            'folders' => MockData::folders($id),
        ]);
    }

    #[GetMapping('buckets/{id}/tokens')]
    public function tokens(#[PathVariable] string $id): ResponseView
    {
        $bucket = $this->requireBucket($id);

        return $this->page('bucket-tokens', $bucket['name'], 'tokens', [
            'subtitle' => 'ключи клиента к каналу /p',
            'bucket' => $bucket,
            'tokens' => MockData::tokens($id),
        ]);
    }

    #[GetMapping('buckets/{id}/links')]
    public function links(#[PathVariable] string $id): ResponseView
    {
        $bucket = $this->requireBucket($id);

        return $this->page('bucket-links', $bucket['name'], 'links', [
            'subtitle' => 'канал /t — доступ даёт подпись',
            'bucket' => $bucket,
            'links' => MockData::links($id),
        ]);
    }

    /** Отдельная страница без каркаса админки. */
    #[GetMapping('login')]
    public function login(): ResponseView
    {
        return ResponseView::view('admin/login');
    }

    private function requireBucket(string $id): array
    {
        $bucket = MockData::bucket($id);
        if ($bucket === null) {
            ClientError::throw('Bucket not found', HttpCode::NOT_FOUND);
        }

        return $bucket;
    }

    /** Список бакетов нужен каркасу на каждой странице — для переключателя. */
    private function page(string $resource, string $title, string $nav, array $data = []): ResponseView
    {
        return ResponseView::render('layouts/admin', 'admin/' . $resource, $data + [
            'title' => $title,
            'nav' => $nav,
            'buckets' => MockData::buckets(),
        ]);
    }
}
