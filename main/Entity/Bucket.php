<?php

declare(strict_types=1);

namespace Main\Entity;

use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Constraint\CheckEnum;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Entity\Table;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Hybrid\UuidPk;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Idx\Unique;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\BigInteger;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Integer;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\SmallInteger;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Timestamp;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Varchar;
use Main\Enum\BucketStatus;
use Main\Enum\CacheVisibility;

#[Table]
class Bucket
{
    #[UuidPk]
    public ?string $id = null;

    #[Unique]
    #[Varchar(100)]
    public string $name;

    #[Varchar(255)]
    public string $description = '';

    #[BigInteger]
    public int $quota_bytes = 104857600;

    #[BigInteger]
    public int $used_bytes = 0;

    #[SmallInteger]
    #[CheckEnum(BucketStatus::class)]
    public int $status = BucketStatus::CREATED->value;

    #[Integer]
    public ?int $cache_max_age = null;

    #[SmallInteger]
    #[CheckEnum(CacheVisibility::class)]
    public int $cache_visibility = CacheVisibility::SHARED->value;

    #[BigInteger]
    public int $link_epoch = 1;

    #[Timestamp]
    public string $created_at;

    #[Timestamp]
    public string $updated_at;
}
