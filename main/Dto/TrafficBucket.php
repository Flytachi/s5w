<?php

declare(strict_types=1);

namespace Main\Dto;

final class TrafficBucket
{
    public string $bucket_id = '';
    public string $name = '';
    public int $egress = 0;
    public int $ingress = 0;
    public int $deliveries = 0;
    public int $api = 0;

    public function averageServed(): int
    {
        return $this->deliveries > 0 ? intdiv($this->egress, $this->deliveries) : 0;
    }
}
