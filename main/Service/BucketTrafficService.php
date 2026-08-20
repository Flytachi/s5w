<?php

declare(strict_types=1);

namespace Main\Service;

use Flytachi\Winter\Cdo\CDOBind;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Main\Dto\TrafficDay;
use Main\Dto\TrafficTotals;
use Main\Repository\BucketTrafficRepository;

/**
 * Чтение статистики бакета.
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

    /**
     * Суточный ряд за отрезок, без пропусков.
     *
     * Дни без трафика база не вернёт вовсе, а графику нужен непрерывный ряд — иначе
     * тихий вторник просто исчезнет с оси, и неделя из пяти столбиков будет выглядеть
     * как неделя из пяти дней. Поэтому недостающие дни дозаполняются нулями здесь.
     *
     * @return list<TrafficDay> от $from к $to включительно
     */
    public function daily(string $bucketId, string $from, string $to, string $timezone): array
    {
        $sql = sprintf(
            'SELECT to_char(date_trunc(\'day\', period AT TIME ZONE :tz), \'YYYY-MM-DD\') AS day,'
            . ' sum(egress_bytes)::bigint AS egress, sum(ingress_bytes)::bigint AS ingress,'
            . ' sum(delivery_hits)::bigint AS deliveries, sum(api_hits)::bigint AS api'
            . ' FROM %s WHERE bucket_id = :bucket'
            . ' AND period >= (:from::date)::timestamp AT TIME ZONE :tz'
            . ' AND period <  ((:to::date) + 1)::timestamp AT TIME ZONE :tz'
            . ' GROUP BY 1 ORDER BY 1',
            $this->repo->originTable(),
        );

        $rows = [];
        foreach ($this->repo->rawFetch($sql, [
            new CDOBind('tz', $timezone),
            new CDOBind('bucket', $bucketId),
            new CDOBind('from', $from),
            new CDOBind('to', $to),
        ], TrafficDay::class) as $row) {
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
