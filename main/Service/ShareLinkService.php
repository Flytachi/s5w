<?php

declare(strict_types=1);

namespace Main\Service;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\Cdo\Qb;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Exception\ClientError;
use Flytachi\Winter\Kernel\Unit\Pagination\WrapResult;
use Flytachi\Winter\Kernel\Unit\Wrapper;
use Main\Dto\ShareLinkRes;
use Main\Entity\ShareLink;
use Main\Enum\Disposition;
use Main\Repository\BucketRepository;
use Main\Repository\ShareLinkRepository;
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
