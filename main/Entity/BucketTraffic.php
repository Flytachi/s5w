<?php

declare(strict_types=1);

namespace Main\Entity;

use Flytachi\Winter\Ppa\Mapping\Attributes\Constraint\ForeignRepo;
use Flytachi\Winter\Ppa\Mapping\Attributes\Entity\Table;
use Flytachi\Winter\Ppa\Mapping\Attributes\Hybrid\BigId;
use Flytachi\Winter\Ppa\Mapping\Attributes\Idx\Unique;
use Flytachi\Winter\Ppa\Mapping\Attributes\Primal\BigInteger;
use Flytachi\Winter\Ppa\Mapping\Attributes\Primal\Timestamp;
use Flytachi\Winter\Ppa\Mapping\Attributes\Primal\Uuid;
use Flytachi\Winter\Ppa\Mapping\Constants\FKAction;
use Main\Repository\BucketRepository;

#[Table]
class BucketTraffic
{
    #[BigId]
    public ?int $id = null;

    #[Uuid]
    #[ForeignRepo(
        BucketRepository::class,
        FKAction::CASCADE,
        FKAction::CASCADE,
    )]
    #[Unique(['period'])]
    public string $bucket_id;

    /** Начало часа, всегда в UTC. Пересчёт в пояс смотрящего — дело запроса. */
    #[Timestamp]
    public string $period;

    /** Отдано клиентам. Тот самый ресурс, который у хостера стоит денег. */
    #[BigInteger]
    public int $egress_bytes = 0;

    /**
     * Принято от клиентов.
     *
     * Не то же самое, что прирост хранения: дедупликация даёт трафик при нулевом
     * приросте, а брошенная чанковая загрузка тратит канал и место в staging, не
     * становясь файлом никогда. Ни то, ни другое по `used_bytes` не видно.
     */
    #[BigInteger]
    public int $ingress_bytes = 0;

    /** Обращения к раздаче: `/o`, `/p`, `/t` — включая 304, HEAD и 416. */
    #[BigInteger]
    public int $delivery_hits = 0;

    /** Обращения с токеном доступа — нагрузка на управляющий контур. */
    #[BigInteger]
    public int $api_hits = 0;
}
