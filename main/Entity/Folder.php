<?php

declare(strict_types=1);

namespace Main\Entity;

use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Constraint\CheckEnum;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Constraint\ForeignRepo;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Entity\Table;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Hybrid\BigId;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Idx\Unique;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Boolean;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Integer;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\SmallInteger;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Timestamp;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Uuid;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Varchar;
use Flytachi\Winter\Kernel\Ppa\Mapping\Constants\FKAction;
use Main\Enum\CacheVisibility;
use Main\Enum\Retention;
use Main\Repository\BucketRepository;

#[Table]
class Folder
{
    #[BigId]
    public ?int $id = null;

    #[Uuid]
    #[ForeignRepo(
        BucketRepository::class,
        FKAction::CASCADE,
        FKAction::CASCADE,
    )]
    #[Unique(['name'])]
    public string $bucket_id;

    #[Varchar(100)]
    public string $name;

    #[Boolean]
    public bool $public = false;

    #[SmallInteger]
    #[CheckEnum(Retention::class)]
    public int $retention = Retention::NONE->value;

    #[Integer]
    public ?int $cache_max_age = null;

    #[SmallInteger]
    #[CheckEnum(CacheVisibility::class)]
    public ?int $cache_visibility = null;

    #[Timestamp]
    public string $created_at;

    #[Timestamp]
    public string $updated_at;
}
