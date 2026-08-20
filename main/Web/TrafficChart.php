<?php

declare(strict_types=1);

namespace Main\Web;

use Main\Dto\TrafficDay;

/**
 * Готовые к отрисовке столбики графика расхода.
 *
 * Считает здесь, а рисует шаблон: `wrImport()` включает частичный шаблон с одной лишь
 * поданной контроллером `$data` в области видимости, локальные переменные страницы туда
 * не попадают. Значит частичным шаблоном с параметрами график не сделать, а класть
 * одинаковую арифметику в два шаблона — верный способ развести их со временем.
 *
 * ## Про названия рядов
 *
 * `egress` и `ingress` — то, чем меряют трафик все, у кого за него берут деньги: AWS,
 * GCP, Cloudflare. Инженер читает их без пояснений и, что важнее, не путает
 * направление — в отличие от «отдано/принято», где надо ещё сообразить, чья это точка
 * зрения. Русская расшифровка стоит рядом в легенде, а не вместо.
 */
final class TrafficChart
{
    private const int GRID_LINES = 4;

    /** @param list<Column> $columns @param list<string> $grid */
    private function __construct(
        public readonly array $columns,
        public readonly array $grid,
        public readonly int $peak,
        public readonly bool $inBytes,
        public readonly string $topLabel,
        public readonly string $topHint,
        public readonly string $bottomLabel,
        public readonly string $bottomHint,
    ) {
    }

    /**
     * @param list<TrafficDay> $series
     * @param 'bytes'|'hits' $metric
     */
    public static function of(array $series, string $metric = 'bytes'): self
    {
        $inBytes = $metric === 'bytes';
        $top = static fn(TrafficDay $d): int => $inBytes ? $d->egress : $d->deliveries;
        $bottom = static fn(TrafficDay $d): int => $inBytes ? $d->ingress : $d->api;

        // Шкала общая на оба ряда. Разные шкалы сделали бы ряды визуально сравнимыми,
        // не будучи такими, — это худший вид вранья графиком.
        $peak = 0;
        foreach ($series as $day) {
            $peak = max($peak, $top($day) + $bottom($day));
        }

        // Подпись под каждым столбцом на месячном отрезке сливается в кашу.
        $step = max(1, (int) ceil(count($series) / 12));
        $fmt = static fn(int $v): string => $inBytes ? Fmt::bytes($v) : Fmt::num($v);

        $columns = [];
        foreach ($series as $i => $day) {
            $t = $top($day);
            $b = $bottom($day);
            $columns[] = new Column(
                day: $day->day,
                dayTitle: $day->title(),
                label: $i % $step === 0 ? $day->label() : '',
                // Нулевой пик означает пустой отрезок: без этой проверки было бы деление
                // на ноль, а с нулевой шкалой все столбики вышли бы одной высоты и график
                // соврал бы про равномерность.
                topPercent: $peak > 0 ? round($t / $peak * 100, 2) : 0.0,
                bottomPercent: $peak > 0 ? round($b / $peak * 100, 2) : 0.0,
                topValue: $fmt($t),
                bottomValue: $fmt($b),
                isEmpty: $t === 0 && $b === 0,
            );
        }

        return new self(
            columns: $columns,
            grid: self::grid($peak, $fmt),
            peak: $peak,
            inBytes: $inBytes,
            topLabel: $inBytes ? 'Egress' : 'Delivery',
            topHint: $inBytes ? 'исходящий трафик' : 'запросы к раздаче',
            bottomLabel: $inBytes ? 'Ingress' : 'API',
            bottomHint: $inBytes ? 'входящий трафик' : 'запросы с токеном',
        );
    }

    public function isEmpty(): bool
    {
        return $this->peak === 0;
    }

    public function peakLabel(): string
    {
        return $this->inBytes ? Fmt::bytes($this->peak) : Fmt::num($this->peak);
    }

    /**
     * Подписи горизонтальных линий, сверху вниз.
     *
     * Без них высота столбика — это «больше вон того», и только. С ними её можно
     * прочитать, не наводя мышь, а на печати и в скриншоте мышь навести нельзя вовсе.
     *
     * @return list<string>
     */
    private static function grid(int $peak, callable $fmt): array
    {
        if ($peak <= 0) {
            return [];
        }

        $lines = [];
        for ($i = self::GRID_LINES; $i > 0; $i--) {
            $lines[] = $fmt((int) round($peak * $i / self::GRID_LINES));
        }

        return $lines;
    }
}
