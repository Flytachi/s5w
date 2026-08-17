<?php

declare(strict_types=1);

namespace Main\Request;

use Flytachi\Winter\Kernel\Http\Request\Validation\Max;
use Flytachi\Winter\Kernel\Http\Request\Validation\Min;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;
use Main\Enum\ImageFormat;

final class FileUploadRequest
{
    public function __construct(
        #[Size(min: 0, max: 100)]
        public ?string $folder = null,

        #[Size(min: 0, max: 255)]
        public ?string $name = null,

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

    public function wantsImageWork(): bool
    {
        return $this->format !== ImageFormat::ORIGINAL
            || $this->quality !== null
            || $this->maxWidth !== null
            || $this->maxHeight !== null;
    }
}
