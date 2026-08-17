<?php

declare(strict_types=1);

namespace Main\Entity;

use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Constraint\CheckEnum;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Constraint\ForeignRepo;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Entity\Table;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Hybrid\BigId;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Idx\Unique;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Char;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\SmallInteger;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Timestamp;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Uuid;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Varchar;
use Flytachi\Winter\Kernel\Ppa\Mapping\Constants\FKAction;
use Main\Enum\TokenAccess;
use Main\Enum\TokenStatus;
use Main\Repository\BucketRepository;

#[Table]
class AccessToken
{
    #[BigId]
    public ?int $id = null;

    #[Uuid]
    #[ForeignRepo(
        BucketRepository::class,
        FKAction::CASCADE,
        FKAction::CASCADE,
    )]
    public string $bucket_id;

    #[Unique]
    #[Char(64)]
    public string $hash;

    #[Varchar(100)]
    public string $name;

    #[SmallInteger]
    #[CheckEnum(TokenStatus::class)]
    public int $status = TokenStatus::ACTIVE->value;

    #[SmallInteger]
    #[CheckEnum(TokenAccess::class)]
    public int $access = TokenAccess::BASIC->value;

    #[Varchar(4)]
    public string $tail = '';

    #[Timestamp]
    public ?string $expires_at = null;

    #[Timestamp]
    public ?string $last_used_at = null;

    #[Timestamp]
    public string $created_at;
}
