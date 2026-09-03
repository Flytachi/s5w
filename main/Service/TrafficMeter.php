<?php

declare(strict_types=1);

namespace Main\Service;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Schedule\Scheduled;
use Main\Cacheable\CacheRegistry;
use Main\Cacheable\SharedCounters;
use Main\Entity\BucketTraffic;
use Main\Repository\BucketTrafficRepository;
use Psr\Log\LoggerInterface;

/**
 * Расход бакета: трафик в обе стороны и число обращений.
 *
 * Счёт идёт в общей памяти воркеров, в базу уходит пачкой. Иначе каждая выдача
 * означала бы запись в одну и ту же строку — замерено на pgbench: 16 писателей в одну
 * строку дают 1 390 tps против 10 216 по разным, то есть счётчик стал бы самым узким
 * местом сервиса. Здесь же {@see SharedCounters::add()} стоит 0.33 мкс.
 *
 * Байты и обращения считаются в **разных** точках, и это не небрежность:
 * `beforeSend()` срабатывает только когда пойдёт содержимое — не на 304, не на HEAD,
 * не на 416, — а обращение случилось в любом случае. Разница между этими числами и
 * есть эффективность клиентского кэша.
 *
 * @see BucketTraffic описание полей и почему отрезок — час
 */
#[Singleton]
final class TrafficMeter
{
    private const string EGRESS = 'egress_bytes';
    private const string INGRESS = 'ingress_bytes';
    private const string DELIVERY = 'delivery_hits';
    private const string API = 'api_hits';

    /** @var list<string> Порядок неважен, важно что перечислены все. */
    private const array METRICS = [self::EGRESS, self::INGRESS, self::DELIVERY, self::API];

    #[Autowired]
    private BucketTrafficRepository $repo;

    #[Autowired]
    private LoggerInterface $log;

    private SharedCounters $counters;

    public function __construct()
    {
        $this->counters = CacheRegistry::traffic();
    }

    /** Байты, ушедшие клиенту. Зовётся из крючка выдачи — только когда идёт содержимое. */
    public function egress(string $bucketId, int $bytes): void
    {
        $this->counters->add($bucketId . '|' . self::EGRESS, $bytes);
    }

    /** Байты, принятые от клиента: кусок загрузки или тело файла. */
    public function ingress(string $bucketId, int $bytes): void
    {
        $this->counters->add($bucketId . '|' . self::INGRESS, $bytes);
    }

    /** Обращение к раздаче — считается до того, как решится, пойдёт ли содержимое. */
    public function deliveryHit(string $bucketId): void
    {
        $this->counters->add($bucketId . '|' . self::DELIVERY, 1);
    }

    /** Обращение с токеном доступа. */
    public function apiHit(string $bucketId): void
    {
        $this->counters->add($bucketId . '|' . self::API, 1);
    }

    /**
     * Переносит накопленное в базу — одной пачкой на все бакеты и метрики сразу.
     *
     * Двадцать секунд это компромисс между тем, что теряется при аварийном падении
     * контейнера, и числом записей: 180 обновлений строки в час на бакет, при том что
     * обращений за тот же час могут быть миллионы. Ни одна из сторон ни во что не
     * упирается, поэтому число выбрано с запасом в обе стороны.
     *
     * Час берётся в UTC и записывается с явным смещением: слив идёт в процессе
     * планировщика, где никакого запроса нет, а значит и часового пояса клиента тоже —
     * полагаться на умолчание сессии здесь нельзя.
     */
    #[Scheduled(fixedDelay: 20.0, initialDelay: 20.0)]
    public function flush(): void
    {
        $taken = $this->counters->drain();
        if ($taken === []) {
            return;
        }

        $period = gmdate('Y-m-d H:00:00 +00:00');
        $rows = [];

        foreach ($taken as $key => $value) {
            [$bucketId, $metric] = array_pad(explode('|', $key, 2), 2, '');
            if (!in_array($metric, self::METRICS, true)) {
                continue;
            }

            $rows[$bucketId] ??= $this->blank($bucketId, $period);
            $rows[$bucketId]->$metric += $value;
        }

        if ($rows === []) {
            return;
        }

        try {
            // Конфликт по (bucket_id, period) не заменяет, а прибавляет: за час слив
            // приходит примерно 180 раз, и каждый раз строка часа должна подрасти.
            $this->repo->upsertBatch(
                $rows,
                ['bucket_id', 'period'],
                array_combine(
                    self::METRICS,
                    array_fill(0, count(self::METRICS), ':current + :new'),
                ),
            );
        } catch (\Throwable $e) {
            // Накопленное уже унесено из общей памяти, вернуть некуда: теряем один
            // интервал учёта и говорим об этом. Ронять планировщик из-за метрики хуже.
            $this->log->warning(
                'traffic flush failed, ' . count($rows) . ' bucket(s) lost: ' . $e->getMessage(),
            );
        }
    }

    private function blank(string $bucketId, string $period): BucketTraffic
    {
        $row = new BucketTraffic();
        $row->bucket_id = $bucketId;
        $row->period = $period;

        return $row;
    }
}
