<?php

declare(strict_types=1);

namespace Main\Services;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Main\Dto\BucketCardRes;
use Main\Dto\BucketOverviewRes;
use Main\Dto\DashboardRes;
use Main\Dto\TrafficRes;
use Main\Enum\CacheVisibility;

/**
 * Сводки панели, которые не принадлежат ни одной сущности: дашборд, обзор бакета,
 * трафик за отрезок. Даты — в поясе того, кто смотрит.
 */
#[Singleton]
final class PanelService
{
    public const int WINDOW = 30;

    public const int TOP = 6;

    #[Autowired]
    private BucketService $buckets;

    #[Autowired]
    private FolderService $folders;

    #[Autowired]
    private AccessTokenService $tokens;

    #[Autowired]
    private ShareLinkService $links;

    #[Autowired]
    private BucketTrafficService $traffic;

    #[Autowired]
    private UploadService $uploads;

    public function dashboard(\DateTimeZone $tz): DashboardRes
    {
        $today = new \DateTimeImmutable('now', $tz);
        $traffic = $this->traffic(
            null,
            $today->modify('-' . (self::WINDOW - 1) . ' days')->format('Y-m-d'),
            $today->format('Y-m-d'),
            $tz,
        );

        $cards = $this->buckets->cards();
        usort($cards, static fn(BucketCardRes $a, BucketCardRes $b) => $b->percent() <=> $a->percent());
        $withQuota = array_values(array_filter(
            $cards,
            static fn(BucketCardRes $card) => $card->bucket->bytes['quota'] > 0,
        ));

        $served = $this->traffic->topBuckets($traffic->from, $traffic->to, $tz->getName());
        $loud = array_column($served, 'bucket_id');
        $quiet = array_values(array_map(
            static fn(BucketCardRes $card) => $card->bucket->name,
            array_filter($cards, static fn(BucketCardRes $card) => !in_array($card->bucket->id, $loud, true)),
        ));

        return new DashboardRes(
            traffic: $traffic,
            top: array_slice($served, 0, self::TOP),
            topTotal: count($served),
            quiet: $quiet,
            fill: array_slice($withQuota, 0, self::TOP),
            fillTotal: count($withQuota),
            buckets: $this->buckets->counts(),
            blobs: $this->buckets->blobCounts(),
            tokens: $this->tokens->counts(),
            links: $this->links->counts(),
            uploads: $this->uploads->counts(),
        );
    }

    public function overview(string $bucketId, \DateTimeZone $tz): BucketOverviewRes
    {
        $card = $this->buckets->card($bucketId);
        $today = new \DateTimeImmutable('now', $tz);

        return new BucketOverviewRes(
            card: $card,
            traffic: $this->traffic($bucketId, $today->format('Y-m-01'), $today->format('Y-m-d'), $tz),
            folders: $this->folders->cards($bucketId),
            usage: $this->buckets->usage($bucketId),
            placement: $this->buckets->placement($bucketId),
            folderCounts: $this->buckets->folderCounts($bucketId),
            tokens: $this->tokens->counts($bucketId),
            links: $this->links->counts($bucketId),
            cacheDefaultMaxAge: CacheVisibility::from($card->bucket->cache['visibility']['id'])->defaultMaxAge(),
        );
    }

    public function traffic(?string $bucketId, string $from, string $to, \DateTimeZone $tz): TrafficRes
    {
        if ($bucketId !== null) {
            $this->buckets->get($bucketId);
        }
        $series = $this->traffic->daily($bucketId, $from, $to, $tz->getName());

        return new TrafficRes($from, $to, $tz->getName(), $series, $this->traffic->totals($series));
    }
}
