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
}
