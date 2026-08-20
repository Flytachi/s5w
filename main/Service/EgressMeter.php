<?php

declare(strict_types=1);

namespace Main\Service;

use Flytachi\Winter\Cdo\CDOBind;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Schedule\Scheduled;
use Main\Cacheable\CacheRegistry;
use Main\Entity\Bucket;
use Main\Cacheable\SharedCounters;
use Main\Repository\BucketRepository;
use Psr\Log\LoggerInterface;

/**
 * Учёт отданных байт по бакету.
 *
 * Хранение бакета ограничено квотой, тело запроса — потолком, диск — резервом в
 * {@see UploadService}. Трафик не считал никто, хотя для файлового сервиса это
 * единственный ресурс, который стоит денег и растёт без спроса.
 *
 * Считаем то, что действительно ушло в ответ: {@see \Flytachi\Winter\Kernel\Http\Response\ResponseStreamFile::beforeSend()}
 * вызывается только когда пойдёт представление — не на 304, не на 416, не на HEAD, —
 * и получает длину, которую объявит `Content-Length`; у 206 это размер куска, а не
 * файла. Одна честная оговорка, она и в докблоке ядра: это **намерение отдать**, а не
 * факт доставки. `sendfile()` передаёт файл реактору, клиент может отвалиться на
 * середине, и PHP об этом не узнает. Счётчик слегка завышает, и точнее из PHP при
 * `sendfile` не сделает никто.
 */
#[Singleton]
final class EgressMeter
{
    #[Autowired]
    private BucketRepository $buckets;

    #[Autowired]
    private LoggerInterface $log;

    private SharedCounters $counters;

    public function __construct()
    {
        $this->counters = CacheRegistry::egress();
    }

    /** Стоит 0.33 мкс — можно звать на каждой отдаче. */
    public function count(string $bucketId, int $bytes): void
    {
        $this->counters->add($bucketId, $bytes);
    }

    /**
     * Переносит накопленное в базу — по одной записи на бакет, у которого что-то было.
     *
     * Полминуты выбраны так: при 5 000 скачиваниях в секунду из трёх бакетов это три
     * записи за интервал вместо ста пятидесяти тысяч, и конкуренции за строку больше
     * нет — писатель один и приходит раз в тридцать секунд.
     *
     * Цена интервала — то, что переживёт падение контейнера: до тридцати секунд учёта.
     * Для метрики это ничто; если счётчик когда-нибудь станет основанием для счёта
     * клиенту, интервал придётся укоротить и добавить слив по сигналу остановки, а
     * абсолютной точности при таком подходе не будет никогда.
     */
    #[Scheduled(fixedDelay: 30.0, initialDelay: 30.0)]
    public function flush(): void
    {
        $taken = $this->counters->drain();
        if ($taken === []) {
            return;
        }

        // Одним запросом, а не по записи на бакет: один заход в базу, одна транзакция,
        // и по одному захвату строки на бакет за интервал. Удалённый тем временем бакет
        // просто не совпадёт — это не ошибка, а нормальный исход, поэтому и ловить нечего.
        $rows  = [];
        $binds = [];
        $i     = 0;
        foreach ($taken as $bucketId => $bytes) {
            $rows[]  = sprintf('(:id%1$d::uuid, :n%1$d::bigint)', $i);
            $binds[] = new CDOBind("id{$i}", $bucketId);
            $binds[] = new CDOBind("n{$i}", $bytes);
            $i++;
        }

        $sql = sprintf(
            'UPDATE %s b SET egress_bytes = b.egress_bytes + v.delta'
            . ' FROM (VALUES %s) AS v(id, delta) WHERE b.id = v.id RETURNING b.id',
            $this->buckets->originTable(),
            implode(', ', $rows),
        );

        try {
            $this->buckets->rawFetch($sql, $binds, Bucket::class);
        } catch (\Throwable $e) {
            // Накопленное уже унесено из таблицы, вернуть его назад некуда — потеряем
            // один интервал учёта и скажем об этом. Ронять планировщик из-за метрики
            // было бы хуже.
            $this->log->warning('egress flush failed, ' . count($taken) . ' bucket(s) lost: ' . $e->getMessage());
        }
    }
}
