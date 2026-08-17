<?php

declare(strict_types=1);

namespace Main\Service;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\Cdo\Qb;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Exception\ClientError;
use Main\Entity\Bucket;
use Main\Entity\FileEntry;
use Main\Entity\Folder;
use Main\Enum\DeliveryChannel;
use Main\Enum\Disposition;
use Main\Http\BlobResponse;
use Main\Repository\BlobRepository;
use Main\Repository\BucketRepository;
use Main\Repository\FileEntryRepository;
use Main\Repository\FolderRepository;
use Main\Storage\BlobStore;
use Main\Support\LinkSigner;

#[Singleton]
final class DeliveryService
{
    #[Autowired]
    private FileEntryRepository $files;

    #[Autowired]
    private BlobRepository $blobs;

    #[Autowired]
    private FolderRepository $folders;

    #[Autowired]
    private BucketRepository $buckets;

    #[Autowired]
    private BlobStore $store;

    #[Autowired]
    private CachePolicy $cache;

    #[Autowired]
    private LinkSigner $signer;

    #[Autowired]
    private ShareLinkService $links;

    public function public(string $bucketId, string $slug, bool $download): BlobResponse
    {
        $file = $this->findBySlug($bucketId, $slug);
        if (!$file->public) {
            $this->notFound();
        }

        return $this->serve($file, DeliveryChannel::PUBLIC, $download);
    }

    public function private(string $bucketId, string $slug, bool $download): BlobResponse
    {
        return $this->serve(
            $this->findBySlug($bucketId, $slug),
            DeliveryChannel::PRIVATE,
            $download,
        );
    }

    public function temporary(string $token): BlobResponse
    {
        $payload = $this->signer->verify($token);
        if ($payload === null || $payload->expiresAt <= time()) {
            $this->notFound();
        }

        $file = $this->files->findById($payload->fileId);
        if ($file === null) {
            $this->notFound();
        }

        $bucket = $this->bucketOf($file);
        if ($payload->epoch !== $bucket->link_epoch) {
            $this->notFound();
        }

        $this->assertAlive($file);

        $attachment = $payload->attachment;
        $limited = false;

        if ($payload->jti !== null) {
            $link = $this->links->consume($payload->jti, $file->id);
            $attachment = Disposition::from($link->disposition)->isAttachment();
            $limited = $link->max_downloads !== null;
        }

        return $this->response(
            $file,
            $bucket,
            $attachment,
            $this->cache->resolve(
                $file,
                $this->folderOf($file),
                $bucket,
                DeliveryChannel::TEMPORARY,
                linkTtl: $payload->expiresAt - time(),
            ),
            acceptRanges: !$limited,
        );
    }

    private function serve(FileEntry $file, DeliveryChannel $channel, bool $download): BlobResponse
    {
        $this->assertAlive($file);
        $bucket = $this->bucketOf($file);

        return $this->response(
            $file,
            $bucket,
            $download,
            $this->cache->resolve($file, $this->folderOf($file), $bucket, $channel),
        );
    }

    private function response(
        FileEntry $file,
        Bucket $bucket,
        bool $download,
        string $cacheControl,
        bool $acceptRanges = true,
    ): BlobResponse {
        $blob = $this->blobs->findById($file->blob_id);
        if ($blob === null || !$this->store->blobExists($bucket->id, $blob->hash)) {
            throw new \RuntimeException("Blob file vanished for file {$file->id}");
        }

        return new BlobResponse(
            path: $this->store->blobPath($bucket->id, $blob->hash),
            fileName: $file->name,
            mimeType: $file->mime_type,
            attachment: $download || !$this->isInline($file),
            cacheControl: $cacheControl,
            acceptRanges: $acceptRanges,
        );
    }

    private function isInline(FileEntry $file): bool
    {
        $mime = strtolower(trim($file->mime_type));
        if ($mime === 'image/svg+xml' || $mime === 'text/html') {
            return false;
        }

        return str_starts_with($mime, 'image/')
            || str_starts_with($mime, 'video/')
            || str_starts_with($mime, 'audio/')
            || $mime === 'application/pdf'
            || $mime === 'text/plain';
    }

    private function assertAlive(FileEntry $file): void
    {
        if ($file->expires_at !== null && strtotime($file->expires_at) <= time()) {
            $this->notFound();
        }
    }

    private function findBySlug(string $bucketId, string $slug): FileEntry
    {
        $file = $this->files->findBy(Qb::and(
            Qb::eq('slug', $slug),
            Qb::eq('bucket_id', $bucketId),
        ));
        if ($file === null) {
            $this->notFound();
        }

        return $file;
    }

    private function bucketOf(FileEntry $file): Bucket
    {
        $bucket = $this->buckets->findById($file->bucket_id);
        if ($bucket === null) {
            $this->notFound();
        }

        return $bucket;
    }

    private function folderOf(FileEntry $file): ?Folder
    {
        return $file->folder_id === null ? null : $this->folders->findById($file->folder_id);
    }

    private function notFound(): never
    {
        ClientError::throw('Not found', HttpCode::NOT_FOUND);
    }
}
