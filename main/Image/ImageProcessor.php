<?php

declare(strict_types=1);

namespace Main\Image;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Exception\ClientError;
use Flytachi\Winter\Kernel\Exception\ServerError;
use Main\Enum\ImageFormat;
use Main\Request\FileUploadRequest;

#[Singleton]
final class ImageProcessor
{
    private const int MAX_PIXELS = 25_000_000;

    private const array DECODABLE = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
    ];

    public function process(string $srcPath, FileUploadRequest $request): ProcessedImage
    {
        if (!$request->wantsImageWork()) {
            return ProcessedImage::skipped($srcPath, 'no options');
        }
        if (!extension_loaded('gd')) {
            ServerError::throw(
                'Image processing is not available on this server',
                HttpCode::NOT_IMPLEMENTED,
            );
        }

        $info = @getimagesize($srcPath);
        if ($info === false) {
            ClientError::throw('Image processing is not applicable to this file', HttpCode::UNPROCESSABLE_ENTITY);
        }

        [$width, $height] = $info;
        $mime = (string) $info['mime'];

        if (!in_array($mime, self::DECODABLE, true)) {
            ClientError::throw(
                "Image processing is not applicable to {$mime}",
                HttpCode::UNPROCESSABLE_ENTITY,
            );
        }
        if ($this->isAnimated($srcPath, $mime)) {
            return ProcessedImage::skipped($srcPath, 'animated');
        }
        if ($width * $height > self::MAX_PIXELS) {
            ClientError::throw(
                sprintf('Image exceeds pixel limit: %d px (max %d)', $width * $height, self::MAX_PIXELS),
                HttpCode::UNPROCESSABLE_ENTITY,
            );
        }

        $target = $request->format->mime() ?? $mime;
        $this->requireEncoder($target);

        $orientation = $this->orientation($srcPath, $mime);
        $rotates = in_array($orientation, [5, 6, 7, 8], true);

        $box = $rotates
            ? [$request->maxHeight, $request->maxWidth]
            : [$request->maxWidth, $request->maxHeight];
        $scale = $this->scaleFor($width, $height, $box[0], $box[1]);

        $needsWork = $scale < 1.0 || $orientation !== 1 || $target !== $mime || $request->quality !== null;
        if (!$needsWork) {
            return ProcessedImage::skipped($srcPath, 'nothing to do');
        }

        $image = $this->decode($srcPath, $mime);
        $operations = [];

        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);

        if ($scale < 1.0) {
            $image = $this->resize($image, $scale);
            $operations[] = sprintf('resize:%dx%d', imagesx($image), imagesy($image));
        }
        if ($orientation !== 1) {
            $image = $this->applyOrientation($image, $orientation);
            $operations[] = 'exif-rotate';
        }
        if ($target === 'image/jpeg' && $mime !== 'image/jpeg') {
            $image = $this->flatten($image);
        }

        $quality = $request->quality ?? ImageFormat::defaultQuality($target);
        $outPath = $srcPath . '.processed';
        $this->encode($image, $outPath, $target, $quality);
        $operations[] = sprintf('encode:%s@%d', explode('/', $target)[1], $quality);

        $resultWidth = imagesx($image);
        $resultHeight = imagesy($image);
        unset($image);

        clearstatcache(true, $outPath);
        $outSize = (int) filesize($outPath);
        $srcSize = (int) filesize($srcPath);

        if ($scale === 1.0 && $orientation === 1 && $target === $mime && $outSize >= $srcSize) {
            @unlink($outPath);
            return ProcessedImage::skipped($srcPath, 'output is larger than source');
        }

        return ProcessedImage::done(
            $outPath,
            $operations,
            ['width' => $width, 'height' => $height, 'size' => $srcSize, 'mime' => $mime],
            ['width' => $resultWidth, 'height' => $resultHeight, 'size' => $outSize, 'mime' => $target],
        );
    }

    private function scaleFor(int $width, int $height, ?int $maxWidth, ?int $maxHeight): float
    {
        $scale = 1.0;
        if ($maxWidth !== null && $width > $maxWidth) {
            $scale = $maxWidth / $width;
        }
        if ($maxHeight !== null && $height > $maxHeight) {
            $scale = min($scale, $maxHeight / $height);
        }
        return $scale;
    }

    private function resize(\GdImage $image, float $scale): \GdImage
    {
        $width = max(1, (int) round(imagesx($image) * $scale));
        $height = max(1, (int) round(imagesy($image) * $scale));

        $dst = imagecreatetruecolor($width, $height);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        return $dst;
    }

    private function applyOrientation(\GdImage $image, int $orientation): \GdImage
    {
        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        if ($angle !== 0) {
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            $rotated = imagerotate($image, $angle, $transparent === false ? 0 : $transparent);
            if ($rotated !== false) {
                $image = $rotated;
                imagealphablending($image, false);
                imagesavealpha($image, true);
            }
        }
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        return $image;
    }

    private function flatten(\GdImage $image): \GdImage
    {
        $dst = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagealphablending($dst, true);
        imagecopy($dst, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $dst;
    }

    private function decode(string $path, string $mime): \GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/avif' => @imagecreatefromavif($path),
            default => false,
        };
        if ($image === false) {
            ClientError::throw("Image is corrupted or unsupported: {$mime}", HttpCode::UNPROCESSABLE_ENTITY);
        }
        return $image;
    }

    private function encode(\GdImage $image, string $path, string $mime, int $quality): void
    {
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($image, $path, $quality),
            'image/png' => imagepng($image, $path, 9 - (int) round($quality / 100 * 9)),
            'image/gif' => imagegif($image, $path),
            'image/webp' => imagewebp($image, $path, $quality),
            'image/avif' => imageavif($image, $path, $quality),
            default => false,
        };

        if (!$ok || !is_file($path) || filesize($path) === 0) {
            @unlink($path);
            ServerError::throw("Image encoding to {$mime} produced no output");
        }
    }

    private function requireEncoder(string $mime): void
    {
        $available = match ($mime) {
            'image/jpeg' => function_exists('imagejpeg'),
            'image/png' => function_exists('imagepng'),
            'image/gif' => function_exists('imagegif'),
            'image/webp' => function_exists('imagewebp'),
            'image/avif' => function_exists('imageavif'),
            default => false,
        };
        if (!$available) {
            ServerError::throw("Encoding to {$mime} is not supported on this server", HttpCode::NOT_IMPLEMENTED);
        }
    }

    private function orientation(string $path, string $mime): int
    {
        if ($mime !== 'image/jpeg' || !extension_loaded('exif')) {
            return 1;
        }
        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        return $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
    }

    private function isAnimated(string $path, string $mime): bool
    {
        $head = (string) @file_get_contents($path, length: 65536);

        return match ($mime) {
            'image/png' => str_contains($head, 'acTL'),
            'image/webp' => strlen($head) > 20
                && substr($head, 12, 4) === 'VP8X'
                && (ord($head[20]) & 0x02) !== 0,
            'image/gif' => $this->gifFrames($path) > 1,
            default => false,
        };
    }

    private function gifFrames(string $path): int
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return 1;
        }

        $frames = 0;
        $tail = '';
        try {
            while (!feof($handle)) {
                $chunk = $tail . (string) fread($handle, 1_048_576);
                $frames += substr_count($chunk, "\x21\xF9\x04");
                if ($frames > 1) {
                    return $frames;
                }
                $tail = substr($chunk, -2);
            }
        } finally {
            fclose($handle);
        }

        return max(1, $frames);
    }
}
