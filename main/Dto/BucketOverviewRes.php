<?php

declare(strict_types=1);

namespace Main\Dto;

final class BucketOverviewRes
{
    /**
     * @param FolderCardRes[] $folders
     * @param ExtUsage[] $usage Занятое место по расширениям, от большего.
     * @param array{total: int, active: int, full: int, inactive: int, expired: int} $tokens
     * @param array{total: int, active: int, revoked: int, expired: int} $links
     * @param int $cacheDefaultMaxAge `max-age` when the bucket sets none: CACHE_DEFAULT_MAX_AGE or the private one.
     */
    public function __construct(
        public BucketCardRes $card,
        public TrafficRes $traffic,
        public array $folders,
        public array $usage,
        public PlacementCounts $placement,
        public FolderCounts $folderCounts,
        public array $tokens,
        public array $links,
        public int $cacheDefaultMaxAge,
    ) {
    }
}
