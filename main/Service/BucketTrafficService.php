<?php

declare(strict_types=1);

namespace Main\Service;

use Flytachi\Winter\Cdo\CDOBind;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Main\Dto\TrafficBucket;
use Main\Dto\TrafficDay;
use Main\Dto\TrafficTotals;
use Main\Repository\BucketRepository;
use Main\Repository\BucketTrafficRepository;

/**
 * Чтение статистики расхода.
 *
 * Хранится по часам в UTC ({@see \Main\Entity\BucketTraffic}) — сутки собираются здесь,
 * в том часовом поясе, в котором смотрят. Это не украшение: один и тот же час это
 * 20 августа в UTC и 21-е в Ташкенте, и разложить сутки задним числом можно только
 * потому, что записан час, а не дата.
 */
#[Singleton]
final class BucketTrafficService
{
    #[Autowired]
    private BucketTrafficRepository $repo;

    #[Autowired]
    private BucketRepository $buckets;

    /**
     * Суточный ряд за отрезок, без пропусков. `null` вместо бакета — всё хранилище.
     *
     * Дни без трафика база не вернёт вовсе, а графику нужен непрерывный ряд — иначе
     * тихий вторник просто исчезнет с оси, и неделя из пяти столбиков будет выглядеть
     * как неделя из пяти дней. Поэтому недостающие дни дозаполняются нулями здесь.
     *
     * @return list<TrafficDay> от $from к $to включительно
     */
    public function daily(?string $bucketId, string $from, string $to, string $timezone): array
    {
        $binds = [
            new CDOBind('tz', $timezone),
            new CDOBind('from', $from),
            new CDOBind('to', $to),
        ];
        if ($bucketId !== null) {
            $binds[] = new CDOBind('bucket', $bucketId);
        }

        $sql = sprintf(
            'SELECT to_char(date_trunc(\'day\', period AT TIME ZONE :tz), \'YYYY-MM-DD\') AS day,'
            . ' sum(egress_bytes)::bigint AS egress, sum(ingress_bytes)::bigint AS ingress,'
            . ' sum(delivery_hits)::bigint AS deliveries, sum(api_hits)::bigint AS api'
            . ' FROM %s WHERE %s'
            . ' AND period >= (:from::date)::timestamp AT TIME ZONE :tz'
            . ' AND period <  ((:to::date) + 1)::timestamp AT TIME ZONE :tz'
            . ' GROUP BY 1 ORDER BY 1',
            $this->repo->originTable(),
            $bucketId === null ? 'true' : 'bucket_id = :bucket',
        );

        $rows = [];
        foreach ($this->repo->rawFetch($sql, $binds, TrafficDay::class) as $row) {
            $rows[$row->day] = $row;
        }

        $series = [];
        $cursor = new \DateTimeImmutable($from, new \DateTimeZone('UTC'));
        $last = new \DateTimeImmutable($to, new \DateTimeZone('UTC'));
        while ($cursor <= $last) {
            $day = $cursor->format('Y-m-d');
            $series[] = $rows[$day] ?? TrafficDay::empty($day);
            $cursor = $cursor->modify('+1 day');
        }

        return $series;
    }

    /**
     * Кто съел канал за отрезок, от большего к меньшему.
     *
     * Возвращает **все** бакеты с ненулевым трафиком, а не первые N. Обрезать здесь
     * нечего: строки всё равно прочитаны и сгруппированы, `LIMIT` экономит только
     * пересылку десятка строк. Зато вызывающий знает, сколько бакетов вообще было
     * при деле, и может отличить «тихий» от «не влез в список».
     *
     * @return list<TrafficBucket>
     */
    public function topBuckets(string $from, string $to, string $timezone): array
    {
        $sql = sprintf(
            'SELECT t.bucket_id, b.name,'
            . ' sum(t.egress_bytes)::bigint AS egress, sum(t.ingress_bytes)::bigint AS ingress,'
            . ' sum(t.delivery_hits)::bigint AS deliveries, sum(t.api_hits)::bigint AS api'
            . ' FROM %s t JOIN %s b ON b.id = t.bucket_id'
            . ' WHERE t.period >= (:from::date)::timestamp AT TIME ZONE :tz'
            . ' AND t.period <  ((:to::date) + 1)::timestamp AT TIME ZONE :tz'
            . ' GROUP BY 1, 2 HAVING sum(t.egress_bytes) + sum(t.ingress_bytes) > 0'
            . ' ORDER BY egress DESC, deliveries DESC',
            $this->repo->originTable(),
            $this->buckets->originTable(),
        );

        return $this->repo->rawFetch($sql, [
            new CDOBind('tz', $timezone),
            new CDOBind('from', $from),
            new CDOBind('to', $to),
        ], TrafficBucket::class);
    }

    /** @param list<TrafficDay> $series */
    public function totals(array $series): TrafficTotals
    {
        return new TrafficTotals(
            egress: array_sum(array_map(static fn(TrafficDay $d) => $d->egress, $series)),
            ingress: array_sum(array_map(static fn(TrafficDay $d) => $d->ingress, $series)),
            deliveries: array_sum(array_map(static fn(TrafficDay $d) => $d->deliveries, $series)),
            api: array_sum(array_map(static fn(TrafficDay $d) => $d->api, $series)),
        );
    }
}
