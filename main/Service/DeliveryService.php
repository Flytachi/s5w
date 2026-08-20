<?php

declare(strict_types=1);

namespace Main\Service;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\Cdo\Qb;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Exception\ClientError;
use Flytachi\Winter\Kernel\Http\Response\ResponseStreamFile;
use Main\Entity\Bucket;
use Main\Entity\FileEntry;
use Main\Entity\Folder;
use Main\Enum\DeliveryChannel;
use Main\Enum\Disposition;
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

    public function public(string $bucketId, string $slug, bool $download): ResponseStreamFile
    {
        $file = $this->findBySlug($bucketId, $slug);
        if (!$file->public) {
            $this->notFound();
        }

        return $this->serve($file, DeliveryChannel::PUBLIC, $download);
    }

    public function private(string $bucketId, string $slug, bool $download): ResponseStreamFile
    {
        return $this->serve(
            $this->findBySlug($bucketId, $slug),
            DeliveryChannel::PRIVATE,
            $download,
        );
    }

    public function temporary(string $token): ResponseStreamFile
    {
        $payload = $this->signer->verify($token);
        if ($payload === null || $payload->expiresAt <= time()) {
            $this->linkGone();
        }

        $file = $this->files->findById($payload->fileId);
        if ($file === null) {
            $this->linkGone();
        }

        $bucket = $this->bucketOf($file);
        if ($payload->epoch !== $bucket->link_epoch) {
            $this->linkGone();
        }

        if ($file->expires_at !== null && strtotime($file->expires_at) <= time()) {
            $this->linkGone();
        }

        $attachment = $payload->attachment;
        $limited = false;
        $onServe = null;

        if ($payload->jti !== null) {
            // Проверяем сейчас, чтобы мёртвая ссылка получила 404 до всякого ответа,
            // а списываем скачивание только когда байты реально пойдут: ревалидация
            // с 304 ничего не передаёт и лимит тратить не должна.
            $link = $this->links->peek($payload->jti, $file->id);
            $attachment = Disposition::from($link->disposition)->isAttachment();
            $limited = $link->max_downloads !== null;

            if ($limited) {
                $onServe = fn() => $this->links->consume($payload->jti, $file->id);
            }
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
            onServe: $onServe,
        );
    }

    private function serve(FileEntry $file, DeliveryChannel $channel, bool $download): ResponseStreamFile
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
        ?\Closure $onServe = null,
    ): ResponseStreamFile {
        $blob = $this->blobs->findById($file->blob_id);
        if ($blob === null || !$this->store->blobExists($bucket->id, $blob->hash)) {
            throw new \RuntimeException("File content is missing from storage, file {$file->id}");
        }

        return ResponseStreamFile::open(
            $this->store->blobPath($bucket->id, $blob->hash),
            isAttachment: $download || !$this->isInline($file),
        )
            // На диске лежит sha256 без расширения, а показать надо имя и тип из базы:
            // выводить их из пути тут нечего, да и незачем пересниффливать содержимое.
            ->fileName($file->name)
            ->contentType($file->mime_type)
            // Правило кэширования у нас своё — не только max-age, но и private,
            // no-store, immutable. Заголовок пишется после вычисленного и перекрывает его.
            ->header('Cache-Control', $cacheControl)
            ->acceptRanges($acceptRanges)
            ->beforeSend($onServe);
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
        ClientError::throw('File not found', HttpCode::NOT_FOUND);
    }

    private function linkGone(): never
    {
        ClientError::throw('Link is invalid or has expired', HttpCode::NOT_FOUND);
    }
}
