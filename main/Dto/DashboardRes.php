<?php

declare(strict_types=1);

namespace Main\Dto;

final class DashboardRes
{
    /**
     * @param TrafficBucket[] $top Самые нагруженные бакеты отрезка.
     * @param string[] $quiet Имена бакетов без трафика в байтах за отрезок (отдача + приём = 0);
     *                        обращения без тела (304, HEAD, API) сюда не влияют.
     * @param BucketCardRes[] $fill Бакеты с квотой, от самых заполненных.
     * @param array{total: int, active: int, full: int, inactive: int, expired: int} $tokens
     * @param array{total: int, active: int, revoked: int, expired: int} $links
     */
    public function __construct(
        public TrafficRes $traffic,
        public array $top,
        public int $topTotal,
        public array $quiet,
        public array $fill,
        public int $fillTotal,
        public BucketCounts $buckets,
        public BlobCounts $blobs,
        public array $tokens,
        public array $links,
        public UploadCounts $uploads,
    ) {
    }
}
