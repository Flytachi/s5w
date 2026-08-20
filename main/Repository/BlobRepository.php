<?php

declare(strict_types=1);

namespace Main\Repository;

use Flytachi\Winter\Ppa\Stereotype\Repository;
use Main\Configuration\MainDbConfig;
use Main\Entity\Blob;

/**
 * @extends Repository<Blob>
 */
final class BlobRepository extends Repository
{
    protected string $dbConfigClassName = MainDbConfig::class;
    protected string $entityClassName = Blob::class;
    public static string $table = 'blobs';
}
