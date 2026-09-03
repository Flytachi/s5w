<?php

declare(strict_types=1);

namespace Main\Request;

use Flytachi\Winter\Kernel\Http\Request\Validation\Max;
use Flytachi\Winter\Kernel\Http\Request\Validation\Min;
use Flytachi\Winter\Kernel\Http\Request\Validation\Positive;
use Flytachi\Winter\Kernel\Http\Request\Validation\Required;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;
use Main\Enum\ImageFormat;

final class UploadStartRequest
{
    public function __construct(
        #[Required]
        #[Size(min: 1, max: 255)]
        public string $name,

        #[Required]
        #[Positive]
        public int $size,

        #[Size(min: 0, max: 100)]
        public ?string $folder = null,

        /** Хеш содержимого: если такое уже лежит в бакете, загрузка не понадобится. */
        #[Size(min: 64, max: 64)]
        public ?string $sha256 = null,

        public ImageFormat $format = ImageFormat::ORIGINAL,

        #[Min(1)]
        #[Max(100)]
        public ?int $quality = null,

        #[Min(16)]
        #[Max(10000)]
        public ?int $maxWidth = null,

        #[Min(16)]
        #[Max(10000)]
        public ?int $maxHeight = null,
    ) {
    }

    public function toFileRequest(): FileUploadRequest
    {
        return new FileUploadRequest(
            folder: $this->folder,
            name: $this->name,
            format: $this->format,
            quality: $this->quality,
            maxWidth: $this->maxWidth,
            maxHeight: $this->maxHeight,
        );
    }
}
