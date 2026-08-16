<?php

declare(strict_types=1);

namespace Main\Service;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\Cdo\Qb;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Exception\ClientError;
use Flytachi\Winter\Kernel\Unit\Pagination\WrapMeta;
use Flytachi\Winter\Kernel\Unit\Pagination\WrapResult;
use Flytachi\Winter\Kernel\Unit\Wrapper;
use Main\Dto\ShareLinkRes;
use Main\Entity\FileEntry;
use Main\Entity\ShareLink;
use Main\Enum\Disposition;
use Main\Enum\LinkPurge;
use Main\Repository\BucketRepository;
use Main\Repository\FileEntryRepository;
use Main\Repository\ShareLinkRepository;
use Main\Request\LinkListRequest;
use Main\Request\PageRequest;
use Main\Request\ShareLinkRequest;
use Main\Support\LinkPayload;
use Main\Support\LinkSigner;

#[Singleton]
final class ShareLinkService
{
    #[Autowired]
    private ShareLinkRepository $repo;

    #[Autowired]
    private BucketRepository $buckets;

    #[Autowired]
    private FileEntryRepository $fileRepo;

    #[Autowired]
    private FileService $files;

    #[Autowired]
    private LinkSigner $signer;

    public function issue(
        string $bucketId,
        string $slug,
        ShareLinkRequest $request,
        string $baseUrl,
    ): ShareLinkRes {
        $file = $this->files->get($bucketId, $slug);
        $bucket = $this->buckets->findById($bucketId);
        if ($bucket === null) {
            ClientError::throw('Bucket not found', HttpCode::NOT_FOUND);
        }

        $expiresAt = time() + $request->ttl;
        $stateful = $request->revocable || $request->maxDownloads !== null;

        if (!$stateful) {
            $token = $this->signer->sign(new LinkPayload(
                fileId: $file->id,
                expiresAt: $expiresAt,
                attachment: $request->disposition->isAttachment(),
                epoch: $bucket->link_epoch,
            ));

            return ShareLinkRes::stateless(
                $this->url($baseUrl, $token),
                $this->format($expiresAt),
                $request->disposition,
            );
        }

        $link = new ShareLink();
        $link->bucket_id = $bucketId;
        $link->file_id = $file->id;
        $link->expires_at = $this->format($expiresAt);
        $link->max_downloads = $request->maxDownloads;
        $link->disposition = $request->disposition->value;
        $link->note = $request->note;
        $link->created_at = date('Y-m-d H:i:s P');
        $link->id = $this->repo->insert($link);

        $token = $this->signer->sign(new LinkPayload(
            fileId: $file->id,
            expiresAt: $expiresAt,
            attachment: $request->disposition->isAttachment(),
            epoch: $bucket->link_epoch,
            jti: $link->id,
        ));

        return ShareLinkRes::from($link, $this->url($baseUrl, $token));
    }

    public function consume(int $id, int $fileId): ShareLink
    {
        $db = $this->repo->db();
        $db->beginTransaction();

        try {
            $link = $this->repo->where(Qb::eq('id', $id))->forBy('UPDATE')->find();

            if (
                $link === null
                || $link->file_id !== $fileId
                || $link->revoked
                || strtotime($link->expires_at) <= time()
                || ($link->max_downloads !== null && $link->downloads >= $link->max_downloads)
            ) {
                ClientError::throw('Not found', HttpCode::NOT_FOUND);
            }

            if ($link->max_downloads !== null) {
                $link->downloads++;
                $this->repo->update(['downloads' => $link->downloads], Qb::eq('id', $id));
            }

            $db->commit();

            return $link;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function getAll(string $bucketId, PageRequest $request, string $baseUrl): WrapResult
    {
        return Wrapper::paginator(
            $this->repo->where(Qb::eq('bucket_id', $bucketId))->orderBy('created_at DESC'),
            $request->limit,
            $request->page,
            mapper: fn(ShareLink $link) => ShareLinkRes::from($link, ''),
        );
    }

    /**
     * Ссылки бакета для панели — вместе с файлом, на который выданы.
     *
     * Поиск и сортировка идут по всему бакету, а не по видимой странице, и
     * считаются здесь, а не в SQL: сортировать надо по имени файла (другая
     * таблица) и по состоянию (его в колонках нет — это отзыв, срок и остаток
     * лимита вместе). Пока строк сотни, честная выборка в память дешевле
     * коррелированных подзапросов; когда пойдут тысячи — здесь понадобится
     * join и вычисляемая колонка состояния.
     *
     * @return array{meta: \Flytachi\Winter\Kernel\Unit\Pagination\WrapMeta, items: array<int, array{link: ShareLink, file: ?FileEntry, url: string}>}
     */
    public function panelPage(string $bucketId, LinkListRequest $request, string $baseUrl = ''): array
    {
        $links = $this->repo->findAllBy(Qb::eq('bucket_id', $bucketId));
        $epoch = $this->buckets->findById($bucketId)?->link_epoch ?? 0;

        $files = [];
        if ($links !== []) {
            foreach ($this->fileRepo->findAllBy(Qb::in('id', array_column($links, 'file_id'))) as $file) {
                $files[$file->id] = $file;
            }
        }

        // Адрес собираем той же подписью, что и при выпуске: она детерминирована,
        // так что скопировать ссылку можно и потом (см. forFile()).
        $rows = array_map(
            fn(ShareLink $link) => [
                'link' => $link,
                'file' => $files[$link->file_id] ?? null,
                'url' => $baseUrl === '' ? '' : $this->url($baseUrl, $this->signer->sign(new LinkPayload(
                    fileId: $link->file_id,
                    expiresAt: (int) strtotime($link->expires_at),
                    attachment: Disposition::from($link->disposition)->isAttachment(),
                    epoch: $epoch,
                    jti: $link->id,
                ))),
            ],
            $links,
        );

        $search = trim((string) $request->search);
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
                $haystack = mb_strtolower(($row['file']?->name ?? '') . ' ' . $row['link']->note);

                return str_contains($haystack, $needle);
            }));
        }

        usort($rows, fn(array $a, array $b) => $this->compare($a, $b, $request->sort) * ($request->dir === 'asc' ? 1 : -1));

        $total = count($rows);
        $pages = (int) ceil($total / $request->limit);
        $current = max(1, min($request->page, max(1, $pages)));

        return [
            'meta' => new WrapMeta(
                current: $current,
                size: $request->limit,
                total: $total,
                pages: $pages,
                previous: $current > 1 ? $current - 1 : null,
                next: $current < $pages ? $current + 1 : null,
            ),
            'items' => array_slice($rows, ($current - 1) * $request->limit, $request->limit),
        ];
    }

    /**
     * Порядок в убывании: сначала самые свежие, самые «живые», а среди равных —
     * снова по дате, чтобы страницы не перемешивались между запросами.
     */
    private function compare(array $a, array $b, string $sort): int
    {
        $byDate = static fn(array $x, array $y): int => [$x['link']->created_at, $x['link']->id]
            <=> [$y['link']->created_at, $y['link']->id];

        return match ($sort) {
            'file' => [mb_strtolower($a['file']?->name ?? '')] <=> [mb_strtolower($b['file']?->name ?? '')]
                ?: $byDate($a, $b),
            'mode' => $a['link']->disposition <=> $b['link']->disposition ?: $byDate($a, $b),
            'state' => $this->stateRank($a['link']) <=> $this->stateRank($b['link']) ?: $byDate($a, $b),
            default => $byDate($a, $b),
        };
    }

    /** Чем больше, тем «живее»: так убывание ставит рабочие ссылки первыми. */
    private function stateRank(ShareLink $link): int
    {
        return match (true) {
            $link->revoked => 0,
            strtotime($link->expires_at) <= time() => 1,
            $link->max_downloads !== null && $link->downloads >= $link->max_downloads => 2,
            default => 3,
        };
    }

    /**
     * Ссылки, выданные на один файл, — для его карточки.
     *
     * Видно только те, у которых есть строка: ссылка без отзыва и лимита живёт
     * целиком в подписи, и знать о её существовании нам неоткуда.
     *
     * Адрес собираем заново: подпись — это HMAC от полей, которые все лежат в
     * строке (файл, срок, режим, эпоха бакета, id ссылки), так что она выходит
     * ровно та же. Хранить сам токен для этого не нужно.
     *
     * @return ShareLinkRes[]
     */
    public function forFile(string $bucketId, string $slug, string $baseUrl, int $limit = 20): array
    {
        $file = $this->files->get($bucketId, $slug);
        $bucket = $this->buckets->findById($bucketId);
        if ($bucket === null) {
            ClientError::throw('Bucket not found', HttpCode::NOT_FOUND);
        }

        $links = $this->repo
            ->where(Qb::and(Qb::eq('bucket_id', $bucketId), Qb::eq('file_id', $file->id)))
            ->orderBy('created_at DESC')
            ->limit($limit)
            ->findAll();

        return array_map(
            fn(ShareLink $link) => ShareLinkRes::from($link, $this->url($baseUrl, $this->signer->sign(
                new LinkPayload(
                    fileId: $link->file_id,
                    expiresAt: (int) strtotime($link->expires_at),
                    attachment: Disposition::from($link->disposition)->isAttachment(),
                    epoch: $bucket->link_epoch,
                    jti: $link->id,
                ),
            ))),
            $links,
        );
    }

    /** Сколько ссылок у бакета: всего, живых и сколько уже можно вычистить. */
    public function counts(string $bucketId): array
    {
        $all = $this->repo->findAllBy(Qb::eq('bucket_id', $bucketId));
        $active = array_filter($all, static fn(ShareLink $link) => !$link->revoked
            && strtotime($link->expires_at) > time());
        $revoked = array_filter($all, static fn(ShareLink $link) => $link->revoked);
        $expired = array_filter($all, static fn(ShareLink $link) => !$link->revoked
            && strtotime($link->expires_at) <= time());

        return [
            'total' => count($all),
            'active' => count($active),
            'revoked' => count($revoked),
            'expired' => count($expired),
        ];
    }

    /**
     * Вычистить мёртвые строки: отозванные или с истёкшим сроком.
     *
     * Живые не трогаем даже случайно — фильтр строится по состоянию, а не по
     * тому, что пришло от клиента. Истёкшие считаем по времени базы: строка
     * после срока всё равно ничего не открывает.
     */
    public function purge(string $bucketId, LinkPurge $state): int
    {
        $now = date('Y-m-d H:i:s P');

        $where = Qb::and(
            Qb::eq('bucket_id', $bucketId),
            match ($state) {
                LinkPurge::REVOKED => Qb::eq('revoked', true),
                LinkPurge::EXPIRED => Qb::and(Qb::eq('revoked', false), Qb::lte('expires_at', $now)),
            },
        );

        $ids = array_column($this->repo->findAllBy($where), 'id');
        if ($ids === []) {
            return 0;
        }

        $this->repo->delete(Qb::in('id', $ids));

        return count($ids);
    }

    /** Отзыв одной ссылки: строка остаётся, чтобы в панели было видно факт. */
    public function revoke(string $bucketId, int $id): void
    {
        $link = $this->repo->findBy(Qb::and(Qb::eq('id', $id), Qb::eq('bucket_id', $bucketId)));
        if ($link === null) {
            ClientError::throw('Link not found', HttpCode::NOT_FOUND);
        }

        $this->repo->update(['revoked' => true], Qb::eq('id', $id));
    }

    public function revokeAll(string $bucketId): int
    {
        $bucket = $this->buckets->findById($bucketId);
        if ($bucket === null) {
            ClientError::throw('Bucket not found', HttpCode::NOT_FOUND);
        }

        $epoch = $bucket->link_epoch + 1;
        $this->buckets->update(
            ['link_epoch' => $epoch, 'updated_at' => date('Y-m-d H:i:s P')],
            Qb::eq('id', $bucketId),
        );

        return $epoch;
    }

    private function url(string $baseUrl, string $token): string
    {
        return rtrim($baseUrl, '/') . '/t/' . $token;
    }

    private function format(int $timestamp): string
    {
        return date('Y-m-d H:i:s P', $timestamp);
    }
}
