<?php

declare(strict_types=1);

namespace Main\Entity;

use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Constraint\CheckEnum;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Constraint\ForeignRepo;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Entity\Table;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Hybrid\UuidPk;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Idx\Index;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\BigInteger;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Char;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Integer;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\SmallInteger;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Text;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Timestamp;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Uuid;
use Flytachi\Winter\Kernel\Ppa\Mapping\Attributes\Primal\Varchar;
use Flytachi\Winter\Kernel\Ppa\Mapping\Constants\FKAction;
use Main\Enum\ImageFormat;
use Main\Repository\BucketRepository;
use Main\Repository\FolderRepository;

#[Table]
class Upload
{
    #[UuidPk]
    public ?string $id = null;

    #[Uuid]
    #[ForeignRepo(
        BucketRepository::class,
        FKAction::CASCADE,
        FKAction::CASCADE,
    )]
    public string $bucket_id;

    #[BigInteger]
    #[ForeignRepo(
        FolderRepository::class,
        FKAction::CASCADE,
        FKAction::CASCADE,
    )]
    public ?int $folder_id = null;

    #[Varchar(255)]
    public string $name;

    #[BigInteger]
    public int $size_bytes;

    #[BigInteger]
    public int $offset_bytes = 0;

    /** Заявленный клиентом хеш: до загрузки — ключ дедупликации, после — сверка. */
    #[Char(64)]
    public ?string $sha256 = null;

    /** Состояние sha256, докатываемое каждым куском: на финале файл заново не читается. */
    #[Text]
    public string $hash_state;

    #[Varchar(16)]
    #[CheckEnum(ImageFormat::class)]
    public string $format = ImageFormat::ORIGINAL->value;

    #[SmallInteger]
    public ?int $quality = null;

    #[Integer]
    public ?int $max_width = null;

    #[Integer]
    public ?int $max_height = null;

    #[Timestamp]
    #[Index]
    public string $expires_at;

    #[Timestamp]
    public string $created_at;

    #[Timestamp]
    public string $updated_at;
}
