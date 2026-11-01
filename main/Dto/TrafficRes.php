<?php

declare(strict_types=1);

namespace Main\Dto;

final class TrafficRes
{
    /**
     * @param TrafficDay[] $series По дню на каждые сутки отрезка, пустые тоже.
     */
    public function __construct(
        public string $from,
        public string $to,
        public string $timezone,
        public array $series,
        public TrafficTotals $totals,
    ) {
    }
}
