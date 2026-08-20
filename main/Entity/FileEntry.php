<?php

declare(strict_types=1);

namespace Main\Entity;

use Flytachi\Winter\Ppa\Mapping\Attributes\Constraint\ForeignRepo;
use Flytachi\Winter\Ppa\Mapping\Attributes\Entity\Table;
use Flytachi\Winter\Ppa\Mapping\Attributes\Hybrid\BigId;
use Flytachi\Winter\Ppa\Mapping\Attributes\Idx\Index;
use Flytachi\Winter\Ppa\Mapping\Attributes\Idx\Unique;
use Flytachi\Winter\Ppa\Mapping\Attributes\Primal\BigInteger;
use Flytachi\Winter\Ppa\Mapping\Attributes\Primal\Boolean;
use Flytachi\Winter\Ppa\Mapping\Attributes\Primal\Char;
use Flytachi\Winter\Ppa\Mapping\Attributes\Primal\Timestamp;
use Flytachi\Winter\Ppa\Mapping\Attributes\Primal\Uuid;
use Flytachi\Winter\Ppa\Mapping\Attributes\Primal\Varchar;
use Flytachi\Winter\Ppa\Mapping\Constants\FKAction;
use Main\Repository\BlobRepository;
use Main\Repository\BucketRepository;
use Main\Repository\FolderRepository;
use Main\Support\Slug;

#[Table]
class FileEntry
{
    #[BigId]
    public ?int $id = null;

    #[Unique]
    #[Char(Slug::LENGTH)]
    public string $slug;

    #[Uuid]
    #[ForeignRepo(
        BucketRepository::class,
        FKAction::CASCADE,
        FKAction::CASCADE,
    )]
    #[Unique(['name'], where: 'folder_id IS NULL')]
    #[Unique(['folder_id', 'name'], where: 'folder_id IS NOT NULL')]
    public string $bucket_id;

    #[BigInteger]
    #[ForeignRepo(
        FolderRepository::class,
        FKAction::CASCADE,
        FKAction::CASCADE,
    )]
    public ?int $folder_id = null;

    #[BigInteger]
    #[ForeignRepo(
        BlobRepository::class,
        FKAction::RESTRICT,
        FKAction::CASCADE,
    )]
    public int $blob_id;

    #[Varchar(255)]
    public string $name;

    #[Varchar(127)]
    public string $mime_type;

    #[Varchar(32)]
    public string $extension = '';

    #[Boolean]
    public bool $public = false;

    #[Timestamp]
    #[Index(where: 'expires_at IS NOT NULL')]
    public ?string $expires_at = null;

    #[Timestamp]
    public string $created_at;

    #[Timestamp]
    public string $updated_at;
}
