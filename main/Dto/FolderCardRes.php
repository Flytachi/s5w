<?php

declare(strict_types=1);

namespace Main\Dto;

use Main\Entities\Folder;

final class FolderCardRes
{
    public function __construct(
        public FolderRes $folder,
        public int $files,
    ) {
    }

    public static function from(Folder $folder, int $files): self
    {
        return new self(FolderRes::from($folder), $files);
    }
}
