<?php

declare(strict_types=1);

namespace Main\Http;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;
use Flytachi\Winter\Kernel\Http\Contracts\HttpResponse;
use Flytachi\Winter\Kernel\Http\Response\Sendable;

final class BlobResponse implements Sendable
{
    /**
     * @param \Closure|null $onServe вызывается ровно тогда, когда в ответ пойдут байты:
     *   ни ревалидация с 304, ни отказ по диапазону, ни HEAD содержимого не передают,
     *   и учитывать их как выдачу нельзя.
     */
    public function __construct(
        private readonly string $path,
        private readonly string $fileName,
        private readonly string $mimeType,
        private readonly bool $attachment,
        private readonly string $cacheControl,
        private readonly bool $acceptRanges = true,
        private readonly ?\Closure $onServe = null,
    ) {
    }

    public function send(HttpResponse $response, HttpRequest $request): void
    {
        $size = (int) filesize($this->path);
        $mtime = (int) filemtime($this->path);
        $etag = sprintf('"%x-%x"', $mtime, $size);

        $response->header('ETag', $etag);
        $response->header('Last-Modified', gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        $response->header('Cache-Control', $this->cacheControl);
        $response->header('Accept-Ranges', $this->acceptRanges ? 'bytes' : 'none');

        if ($this->isNotModified($request, $mtime, $etag)) {
            $response->status(HttpCode::NOT_MODIFIED->value);
            $response->end('');
            return;
        }

        $range = $this->acceptRanges ? $this->resolveRange($request, $size, $mtime, $etag) : null;

        if ($range === false) {
            $response->status(HttpCode::REQUESTED_RANGE_NOT_SATISFIABLE->value);
            $response->header('Content-Range', "bytes */{$size}");
            $response->end('');
            return;
        }

        // HEAD ядро раздаёт тем же обработчиком, что и GET, снимая тело уже в адаптере
        // (RFC 9110 §9.3.2). Заголовки клиент получит те же, но байтов не увидит — значит
        // и списывать с лимитированной ссылки нечего.
        if ($this->onServe !== null && $request->getMethod() !== 'HEAD') {
            ($this->onServe)();
        }

        if ($range === null) {
            $response->status(HttpCode::OK->value);
            $this->writeFileHeaders($response, $size);
            $response->sendfile($this->path);
            return;
        }

        [$start, $end] = $range;
        $response->status(HttpCode::PARTIAL_CONTENT->value);
        $this->writeFileHeaders($response, $end - $start + 1);
        $response->header('Content-Range', "bytes {$start}-{$end}/{$size}");
        $response->sendfile($this->path, $start, $end - $start + 1);
    }

    private function writeFileHeaders(HttpResponse $response, int $length): void
    {
        $response->header('Content-Type', $this->mimeType);
        $response->header('Content-Disposition', $this->disposition());
        $response->header('Content-Encoding', 'identity');
        $response->header('Content-Length', (string) $length);
        $response->header('X-Content-Type-Options', 'nosniff');
    }

    private function disposition(): string
    {
        $type = $this->attachment ? 'attachment' : 'inline';
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $this->fileName) ?? 'file';
        $ascii = str_replace(['"', '\\'], '_', $ascii);

        return sprintf(
            "%s; filename=\"%s\"; filename*=UTF-8''%s",
            $type,
            $ascii,
            rawurlencode($this->fileName),
        );
    }

    /**
     * @return array{0:int,1:int}|null|false [start,end] → 206, null → 200, false → 416
     */
    private function resolveRange(HttpRequest $request, int $size, int $mtime, string $etag): array|null|false
    {
        $header = $request->getHeader('Range');
        if ($header === null || !str_starts_with($header, 'bytes=')) {
            return null;
        }

        $ifRange = $request->getHeader('If-Range');
        if ($ifRange !== null) {
            $matches = str_starts_with($ifRange, '"') || str_starts_with($ifRange, 'W/')
                ? $ifRange === $etag
                : strtotime($ifRange) === $mtime;
            if (!$matches) {
                return null;
            }
        }

        $set = substr($header, 6);
        if (str_contains($set, ',')) {
            return null;
        }

        [$startRaw, $endRaw] = array_pad(explode('-', $set, 2), 2, '');

        if ($startRaw === '') {
            $last = (int) $endRaw;
            if ($last <= 0) {
                return false;
            }
            $start = max(0, $size - $last);
            $end = $size - 1;
        } else {
            $start = (int) $startRaw;
            $end = $endRaw === '' ? $size - 1 : (int) $endRaw;
        }

        $end = min($end, $size - 1);
        if ($start > $end || $start >= $size) {
            return false;
        }

        return [$start, $end];
    }

    private function isNotModified(HttpRequest $request, int $mtime, string $etag): bool
    {
        $inm = $request->getHeader('If-None-Match');
        if ($inm !== null) {
            if ($inm === '*') {
                return true;
            }
            $strip = static fn(string $t): string => str_starts_with($t, 'W/') ? substr($t, 2) : $t;
            foreach (explode(',', $inm) as $candidate) {
                if ($strip(trim($candidate)) === $strip($etag)) {
                    return true;
                }
            }
            return false;
        }

        $ims = $request->getHeader('If-Modified-Since');
        $since = $ims !== null ? strtotime($ims) : false;

        return $since !== false && $mtime <= $since;
    }
}
