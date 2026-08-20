<?php

declare(strict_types=1);

namespace Main\Service;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\Cdo\Qb;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Exception\ClientError;
use Flytachi\Winter\Ppa\Pagination\WrapResult;
use Flytachi\Winter\Ppa\Pagination\Wrapper;
use Main\Dto\LinkCounts;
use Main\Dto\ShareLinkRes;
use Main\Entity\Bucket;
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
    private const string ALIVE = 'NOT revoked AND expires_at > now()'
        . ' AND (max_downloads IS NULL OR downloads < max_downloads)';

    private const string STATE_RANK = 'CASE WHEN revoked THEN 0'
        . ' WHEN expires_at <= now() THEN 1'
        . ' WHEN max_downloads IS NOT NULL AND downloads >= max_downloads THEN 2'
        . ' ELSE 3 END';

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
        $bucket = $this->requireBucket($bucketId);

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

        return ShareLinkRes::from($link, $this->urlFor($baseUrl, $link, $bucket->link_epoch));
    }

    /**
     * Проверяет ссылку, ничего не списывая: ответом может оказаться 304, а он
     * ни байта не передаёт и скачиванием не считается.
     */
    public function peek(int $id, int $fileId): ShareLink
    {
        $link = $this->repo->findBy(Qb::eq('id', $id));

        if ($link === null || !$this->usable($link, $fileId)) {
            ClientError::throw('Link has been revoked or has reached its download limit', HttpCode::NOT_FOUND);
        }

        return $link;
    }

    public function consume(int $id, int $fileId): ShareLink
    {
        $db = $this->repo->db();
        $db->beginTransaction();

        try {
            $link = $this->repo->where(Qb::eq('id', $id))->forBy('UPDATE')->find();

            if ($link === null || !$this->usable($link, $fileId)) {
                ClientError::throw('Link has been revoked or has reached its download limit', HttpCode::NOT_FOUND);
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

    private function usable(ShareLink $link, int $fileId): bool
    {
        return $link->file_id === $fileId
            && !$link->revoked
            && strtotime($link->expires_at) > time()
            && ($link->max_downloads === null || $link->downloads < $link->max_downloads);
    }

    public function getAll(string $bucketId, PageRequest $request, string $baseUrl): WrapResult
    {
        $epoch = $this->requireBucket($bucketId)->link_epoch;

        return Wrapper::paginator(
            $this->repo->where(Qb::eq('bucket_id', $bucketId))->orderBy('created_at DESC'),
            $request->limit,
            $request->page,
            mapper: fn(ShareLink $link) => ShareLinkRes::from($link, $this->urlFor($baseUrl, $link, $epoch)),
        );
    }

    /**
     * @return array{meta: \Flytachi\Winter\Ppa\Pagination\WrapMeta, items: array<int, array{link: ShareLink, file: ?FileEntry, url: string}>}
     */
    public function panelPage(string $bucketId, LinkListRequest $request, string $baseUrl = ''): array
    {
        $where = [Qb::eq('bucket_id', $bucketId)];

        $search = trim((string) $request->search);
        if ($search !== '') {
            $needle = '%' . $search . '%';
            $where[] = Qb::or(
                Qb::like('note', $needle, true),
                Qb::raw(
                    sprintf(
                        'EXISTS (SELECT 1 FROM %s f WHERE f.id = file_id AND f.name ILIKE :search)',
                        $this->fileRepo->originTable(),
                    ),
                    ['search' => $needle],
                ),
            );
        }

        $page = Wrapper::paginator(
            $this->repo->where(Qb::and(...$where))->orderBy($this->orderBy($request)),
            $request->limit,
            $request->page,
        );

        /** @var ShareLink[] $links */
        $links = $page->data;
        $epoch = $this->buckets->findById($bucketId)?->link_epoch ?? 0;

        $files = [];
        if ($links !== []) {
            foreach ($this->fileRepo->findAllBy(Qb::in('id', array_column($links, 'file_id'))) as $file) {
                $files[$file->id] = $file;
            }
        }

        $items = array_map(
            fn(ShareLink $link) => [
                'link' => $link,
                'file' => $files[$link->file_id] ?? null,
                'url' => $baseUrl === '' ? '' : $this->urlFor($baseUrl, $link, $epoch),
            ],
            $links,
        );

        return ['meta' => $page->meta, 'items' => $items];
    }

    private function orderBy(LinkListRequest $request): string
    {
        $dir = $request->dir === 'asc' ? 'ASC' : 'DESC';
        $tail = "created_at {$dir}, id {$dir}";

        return match ($request->sort) {
            'file' => sprintf(
                '(SELECT f.name FROM %s f WHERE f.id = file_id) %s, %s',
                $this->fileRepo->originTable(),
                $dir,
                $tail,
            ),
            'mode' => "disposition {$dir}, {$tail}",
            'state' => sprintf('%s %s, %s', self::STATE_RANK, $dir, $tail),
            default => $tail,
        };
    }

    /**
     * @return ShareLinkRes[]
     */
    public function forFile(string $bucketId, string $slug, string $baseUrl, int $limit = 20): array
    {
        $file = $this->files->get($bucketId, $slug);
        $epoch = $this->requireBucket($bucketId)->link_epoch;

        $links = $this->repo
            ->where(Qb::and(Qb::eq('bucket_id', $bucketId), Qb::eq('file_id', $file->id)))
            ->orderBy('created_at DESC')
            ->limit($limit)
            ->findAll();

        return array_map(
            fn(ShareLink $link) => ShareLinkRes::from($link, $this->urlFor($baseUrl, $link, $epoch)),
            $links,
        );
    }

    /** @return array{total: int, active: int, revoked: int, expired: int} */
    public function counts(string $bucketId): array
    {
        $row = $this->repo
            ->select(
                'count(*) AS total,'
                . ' count(*) FILTER (WHERE ' . self::ALIVE . ') AS active,'
                . ' count(*) FILTER (WHERE revoked) AS revoked,'
                . ' count(*) FILTER (WHERE NOT revoked AND expires_at <= now()) AS expired'
            )
            ->findBy(Qb::eq('bucket_id', $bucketId), LinkCounts::class) ?? new LinkCounts();

        return [
            'total' => $row->total,
            'active' => $row->active,
            'revoked' => $row->revoked,
            'expired' => $row->expired,
        ];
    }

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

    public function revoke(string $bucketId, int $id): void
    {
        $link = $this->repo->findBy(Qb::and(Qb::eq('id', $id), Qb::eq('bucket_id', $bucketId)));
        if ($link === null) {
            ClientError::throw('Share link does not exist', HttpCode::NOT_FOUND);
        }

        $this->repo->update(['revoked' => true], Qb::eq('id', $id));
    }

    public function revokeAll(string $bucketId): int
    {
        $bucket = $this->buckets->findById($bucketId);
        if ($bucket === null) {
            ClientError::throw('Bucket does not exist', HttpCode::NOT_FOUND);
        }

        $epoch = $bucket->link_epoch + 1;
        $this->buckets->update(
            ['link_epoch' => $epoch, 'updated_at' => date('Y-m-d H:i:s P')],
            Qb::eq('id', $bucketId),
        );

        return $epoch;
    }

    private function requireBucket(string $bucketId): Bucket
    {
        $bucket = $this->buckets->findById($bucketId);
        if ($bucket === null) {
            ClientError::throw('Bucket does not exist', HttpCode::NOT_FOUND);
        }

        return $bucket;
    }

    private function urlFor(string $baseUrl, ShareLink $link, int $epoch): string
    {
        return $this->url($baseUrl, $this->signer->sign(new LinkPayload(
            fileId: $link->file_id,
            expiresAt: (int) strtotime($link->expires_at),
            attachment: Disposition::from($link->disposition)->isAttachment(),
            epoch: $epoch,
            jti: $link->id,
        )));
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
