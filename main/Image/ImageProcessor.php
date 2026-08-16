<?php

declare(strict_types=1);

namespace Main\Image;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Exception\ClientError;
use Flytachi\Winter\Kernel\Exception\ServerError;
use Main\Enum\ImageFormat;
use Main\Request\FileUploadRequest;

/**
 * Обработка картинки при загрузке (docs/plan.md §10).
 *
 * Порядок: заголовок → потолок пикселей → декод → ресайз → EXIF-поворот →
 * кодек. Ничего не просили — файл даже не открывается.
 */
#[Singleton]
final class ImageProcessor
{
    /**
     * GD держит картинку несжатым bitmap'ом по 4 байта на пиксель, а поворот
     * на время работы удваивает это. 25 Мп — это 100 МБ на кадр, 200 МБ на пике
     * при memory_limit=256M на воркер. Заодно это защита от «декомпрессионной
     * бомбы»: файл на 40 КБ с холстом 30000×30000.
     */
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
        // GD схлопывает анимацию в первый кадр — это порча файла, а не
        // оптимизация. Такие кладём как есть.
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

        // Ресайз считаем по тому, как картинка выглядит после поворота: у фото
        // с телефона в файле может лежать альбомный кадр с меткой «повернуть».
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
        // Декодер флаги альфы не выставляет: без этого прозрачный png,
        // пересохранённый в webp без ресайза, приедет с чёрным фоном.
        imagealphablending($image, false);
        imagesavealpha($image, true);

        if ($scale < 1.0) {
            $image = $this->resize($image, $scale);
            $operations[] = sprintf('resize:%dx%d', imagesx($image), imagesy($image));
        }
        // Поворот после ресайза: он аллоцирует ещё один кадр, и дешевле делать
        // это на уменьшенной картинке. Результат тот же — рамку ресайза мы уже
        // развернули выше.
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
        // imagedestroy() с PHP 8.0 ничего не делает (в 8.5 он ещё и deprecated):
        // кадр освобождает счётчик ссылок, здесь — на выходе из метода.
        unset($image);

        clearstatcache(true, $outPath);
        $outSize = (int) filesize($outPath);
        $srcSize = (int) filesize($srcPath);

        // Реэнкод «на месте» иногда даёт файл толще исходного — обычно на PNG.
        // Отдавать такое смысла нет: ни байтов не сэкономили, ни качества не
        // прибавили. Геометрию и формат при этом не трогали, значит явную
        // просьбу клиента мы не игнорируем.
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

    // ── Внутреннее ───────────────────────────────────────────────────────────

    /** Во сколько раз уменьшить, чтобы вписать в рамку. 1.0 — не трогать. */
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
        // Без этого прозрачность превращается в чёрный: imagecopyresampled
        // по умолчанию смешивает пиксели с непрозрачным фоном.
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        return $dst;
    }

    /** EXIF-ориентация 1…8 → поворот и отражения. */
    private function applyOrientation(\GdImage $image, int $orientation): \GdImage
    {
        // imagerotate считает угол против часовой, а ориентация задаёт, на
        // сколько кадр нужно довернуть по часовой.
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
        // 2 — зеркало, 4 — зеркало после разворота на 180°, 5 и 7 — отражения
        // по диагоналям, которые из поворота получаются тем же зеркалом.
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        return $image;
    }

    /** JPEG прозрачности не знает — подкладываем белый, иначе получим чёрный фон. */
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
            // У PNG quality — уровень zlib 0…9, а не потери: шкалу разворачиваем,
            // чтобы «меньше качество» означало «меньше файл», как у остальных.
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

    /** Кодек может быть не собран в gd — это состояние сервера, а не запроса. */
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

    /**
     * Анимация по сигнатурам контейнера: у GIF это второй блок Graphic Control
     * Extension, у WebP — флаг ANIM в VP8X, у PNG — чанк acTL.
     */
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

    /**
     * Кадры GIF считаем потоком: первый кадр может занимать мегабайты, и по
     * началу файла второй GCE не увидеть.
     *
     * Сигнатура короткая, и в теле LZW теоретически может встретиться случайно.
     * Ошибка в эту сторону безопасна: статичный gif просто не обработается.
     * Обратная — превращает анимацию в один кадр.
     */
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
