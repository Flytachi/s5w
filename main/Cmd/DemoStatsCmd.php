<?php

declare(strict_types=1);

namespace Main\Cmd;

use Flytachi\Winter\Cdo\Qb;
use Flytachi\Winter\Console\Inc\Cmd;
use Flytachi\Winter\DI\Attribute\Autowired;
use Main\Entities\Bucket;
use Main\Entities\BucketTraffic;
use Main\Enum\BucketStatus;
use Main\Repositories\BucketRepository;
use Main\Repositories\BucketTrafficRepository;

/**
 * Бакет с придуманной историей расхода — чтобы посмотреть, как выглядят графики,
 * не дожидаясь настоящего трафика.
 *
 * Пишет только в бакет `demo-stats` и в его строки `bucket_traffic`; повторный запуск
 * переписывает историю того же бакета заново. Свой бакет узнаётся по имени и по метке в
 * описании, которую ставит create(): бакет `demo-stats` без неё — чужой, и команда его не
 * трогает ни при заполнении, ни при `--drop`.
 * Убрать целиком — удалить бакет: строки статистики уйдут каскадом.
 *
 *   call script main.Cmd.DemoStatsCmd            90 суток
 *   call script main.Cmd.DemoStatsCmd 30         другой отрезок
 *   call script main.Cmd.DemoStatsCmd --drop     снести бакет со всей историей
 *
 * Числа неслучайны по форме, и это важнее правдоподобия: в них зашиты недельный спад
 * на выходных, общий рост и редкие всплески. На ровном белом шуме график выглядит
 * одинаково при любой ошибке в шкале, а на такой истории кривая шкала сразу заметна.
 */
final class DemoStatsCmd extends Cmd
{
    public static string $title = 'fill a demo bucket with made-up traffic history';

    private const string NAME = 'demo-stats';

    /** Метка своего бакета: её ставит create(); бакет с тем же именем без неё — чужой. */
    private const string DESCRIPTION = 'придуманная история расхода для просмотра графиков';

    /** Час пик — вечер; ночью почти тихо. Индекс — час UTC. */
    private const array HOURLY = [
        3, 2, 2, 1, 1, 1, 2, 4, 7, 11, 14, 16,
        17, 16, 15, 16, 18, 22, 26, 28, 25, 18, 11, 6,
    ];

    #[Autowired]
    private BucketRepository $buckets;

    #[Autowired]
    private BucketTrafficRepository $traffic;

    public function handle(): void
    {
        $days = (int) ($this->args['arguments'][1] ?? 90);
        $days = max(1, min(366, $days));

        $bucket = $this->buckets->findBy(Qb::eq('name', self::NAME));
        if ($bucket !== null && $bucket->description !== self::DESCRIPTION) {
            self::printWarning('bucket "' . self::NAME . '" exists but was not created by this command — left untouched');
            self::printInfo('rename or delete it in the panel, then run again');
            return;
        }

        if (array_key_exists('drop', $this->args['options'])) {
            if ($bucket === null) {
                self::printWarning('bucket "' . self::NAME . '" does not exist');
                return;
            }
            $this->buckets->delete(Qb::eq('id', $bucket->id));
            self::printSuccess('dropped bucket ' . self::NAME . ' with its history');
            return;
        }

        if ($bucket === null) {
            $bucket = $this->create();
            self::printInfo('created bucket ' . self::NAME . ' (' . $bucket->id . ')');
        } else {
            $removed = $this->traffic->delete(Qb::eq('bucket_id', $bucket->id));
            self::printInfo('reusing bucket ' . $bucket->id . ', dropped ' . $removed . ' old row(s)');
        }

        $rows = $this->history((string) $bucket->id, $days);
        // Со спредом, а не массивом одним аргументом: insertBatch() принимает iterable,
        // но разворачивает внутри только Traversable — обычный массив уехал бы в базу
        // как одна «сущность» со столбцами 0, 1, 2…
        $this->traffic->insertBatch(...$rows);

        self::printSuccess(sprintf('%d hour(s) of history over %d day(s)', count($rows), $days));
        self::printInfo('in the panel: /buckets/' . $bucket->id . '/stats');
    }

    private function create(): Bucket
    {
        $bucket = new Bucket();
        $bucket->name = self::NAME;
        $bucket->description = self::DESCRIPTION;
        $bucket->quota_bytes = 50 * 1024 * 1024 * 1024;
        $bucket->used_bytes = 0;
        $bucket->status = BucketStatus::ACTIVE->value;
        $bucket->created_at = gmdate('Y-m-d H:i:s +00:00');
        $bucket->updated_at = $bucket->created_at;
        $id = $this->buckets->insert($bucket);
        $bucket->id = is_string($id) ? $id : (string) $id;

        return $bucket;
    }

    /**
     * @return list<BucketTraffic>
     */
    private function history(string $bucketId, int $days): array
    {
        $rows = [];
        $start = new \DateTimeImmutable('-' . ($days - 1) . ' days', new \DateTimeZone('UTC'));
        $start = $start->setTime(0, 0);

        for ($day = 0; $day < $days; $day++) {
            $date = $start->modify('+' . $day . ' days');
            $weekday = (int) $date->format('N');

            // Выходные тише буднего дня примерно вдвое — так выглядит почти любой сервис,
            // которым пользуются с работы.
            $weekly = $weekday >= 6 ? 0.45 : 1.0;
            // Медленный рост от начала отрезка к концу: полтора раза за весь период.
            $trend = 1.0 + 0.5 * ($day / max(1, $days - 1));
            // Раз в пару недель — всплеск: без них график выглядит слишком причёсанным,
            // а пик шкалы никогда не проверяется. Два-три раза, а не семь: день впятеро
            // выше соседей — это уже происшествие, а не всплеск, и на линейной шкале он
            // придавил бы все остальные дни в полоски.
            $spike = random_int(1, 100) <= 5 ? random_int(2, 3) : 1;

            for ($hour = 0; $hour < 24; $hour++) {
                $weight = self::HOURLY[$hour] * $weekly * $trend * $spike;
                $jitter = random_int(70, 130) / 100;
                $deliveries = (int) round($weight * 12 * $jitter);
                if ($deliveries === 0) {
                    continue;
                }

                $row = new BucketTraffic();
                $row->bucket_id = $bucketId;
                $row->period = $date->setTime($hour, 0)->format('Y-m-d H:i:s P');
                // Средний отданный файл — сотни килобайт, с разбросом.
                $row->egress_bytes = $deliveries * random_int(180_000, 900_000);
                // Входящий много меньше исходящего: файлы кладут раз, а отдают многократно.
                $row->ingress_bytes = (int) round($row->egress_bytes * random_int(2, 9) / 100);
                $row->delivery_hits = $deliveries;
                $row->api_hits = (int) round($deliveries * random_int(5, 20) / 100);
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public static function help(): void
    {
        self::printInfo('call script main.Cmd.DemoStatsCmd [days=90] [--drop]');
        self::printInfo('  creates or refills the "' . self::NAME . '" bucket; --drop removes it entirely');
        self::printInfo('  touches only its own bucket: a "' . self::NAME . '" bucket it did not create is left alone');
    }
}
