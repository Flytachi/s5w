<?php

declare(strict_types=1);

namespace Main\Dto;

final class TrafficTotals
{
    public function __construct(
        public int $egress = 0,
        public int $ingress = 0,
        public int $deliveries = 0,
        public int $api = 0,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->egress === 0 && $this->ingress === 0
            && $this->deliveries === 0 && $this->api === 0;
    }

    /** Средний размер отдачи: показывает, чем бакет занят — крупным или мелочью. */
    public function averageServed(): int
    {
        return $this->deliveries > 0 ? intdiv($this->egress, $this->deliveries) : 0;
    }
}
