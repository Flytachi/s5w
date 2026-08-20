<?php

declare(strict_types=1);

namespace Main\Entity;

use Flytachi\Winter\Ppa\Mapping\Attributes\Constraint\ForeignRepo;
use Flytachi\Winter\Ppa\Mapping\Attributes\Entity\Table;
use Flytachi\Winter\Ppa\Mapping\Attributes\Hybrid\BigId;
use Flytachi\Winter\Ppa\Mapping\Attributes\Idx\Unique;
use Flytachi\Winter\Ppa\Mapping\Attributes\Primal\BigInteger;
use Flytachi\Winter\Ppa\Mapping\Attributes\Primal\Char;
use Flytachi\Winter\Ppa\Mapping\Attributes\Primal\Timestamp;
use Flytachi\Winter\Ppa\Mapping\Attributes\Primal\Uuid;
use Flytachi\Winter\Ppa\Mapping\Constants\FKAction;
use Main\Repository\BucketRepository;

#[Table]
class Blob
{
    #[BigId]
    public ?int $id = null;

    #[Uuid]
    #[ForeignRepo(
        BucketRepository::class,
        FKAction::CASCADE,
        FKAction::CASCADE,
    )]
    #[Unique(['hash'])]
    public string $bucket_id;

    #[Char(64)]
    public string $hash;

    #[BigInteger]
    public int $size_bytes;

    #[BigInteger]
    public int $ref_count = 0;

    #[Timestamp]
    public string $created_at;
}
