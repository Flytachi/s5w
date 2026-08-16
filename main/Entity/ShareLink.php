<?php

declare(strict_types=1);

namespace Main\Entity;

use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Constraint\CheckEnum;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Constraint\ForeignRepo;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Entity\Table;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Hybrid\BigId;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Idx\Index;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\BigInteger;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Boolean;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Integer;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\SmallInteger;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Timestamp;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Uuid;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Varchar;
use Flytachi\Winter\Kernel\Ppa\Mapping\Constants\FKAction;
use Main\Enum\Disposition;
use Main\Repository\BucketRepository;
use Main\Repository\FileEntryRepository;


#[Table]
class ShareLink
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

    #[BigInteger]
    #[ForeignRepo(
        FileEntryRepository::class,
        FKAction::CASCADE,
        FKAction::CASCADE,
    )]
    public int $file_id;

    #[Timestamp]
    #[Index]
    public string $expires_at;

    #[Integer]
    public ?int $max_downloads = null;

    #[Integer]
    public int $downloads = 0;

    #[Boolean]
    public bool $revoked = false;

    #[SmallInteger]
    #[CheckEnum(Disposition::class)]
    public int $disposition = Disposition::INLINE->value;

    #[Varchar(255)]
    public string $note = '';

    #[Timestamp]
    public string $created_at;
}
