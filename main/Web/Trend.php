<?php

declare(strict_types=1);

namespace Main\Web;

use Main\Dto\TrafficDay;

/**
 * Неделя к неделе.
 *
 * Сравнивать «сегодня со вчера» на суточном ряде нельзя: сегодняшние сутки ещё идут,
 * и в девять утра любой показатель покажет обвал на две трети. Поэтому берутся семь
 * последних **полных** суток против семи предыдущих — заодно совпадает набор дней
 * недели, а выходные тише будней вдвое и без всякой динамики.
 */
final readonly class Trend
{
    private function __construct(
        public int $current,
        public int $previous,
        public bool $known,
    ) {
    }

    /**
     * @param list<TrafficDay> $series
     * @param callable(TrafficDay): int $pick
     */
    public static function of(array $series, callable $pick, int $window = 7): self
    {
        $sum = static function (array $days) use ($pick): int {
            $total = 0;
            foreach ($days as $day) {
                $total += $pick($day);
            }

            return $total;
        };

        $current = array_slice($series, -($window + 1), $window);
        $previous = array_slice($series, -($window * 2 + 1), $window);

        return new self(
            current: $sum($current),
            previous: $sum($previous),
            known: count($current) === $window && count($previous) === $window && $sum($previous) > 0,
        );
    }

    public function percent(): float
    {
        return $this->known ? ($this->current - $this->previous) / $this->previous * 100 : 0.0;
    }

    public function up(): bool
    {
        return $this->current >= $this->previous;
    }

    public function label(): string
    {
        if (!$this->known) {
            return 'не с чем сравнить';
        }

        $percent = $this->percent();

        return ($percent >= 0 ? '+' : '−') . round(abs($percent)) . '% неделя к неделе';
    }
}
