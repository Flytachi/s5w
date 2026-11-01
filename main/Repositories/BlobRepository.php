<?php

declare(strict_types=1);

namespace Main\Repositories;

use Flytachi\Winter\Ppa\Stereotype\Repository;
use Main\Configuration\MainDbConfig;
use Main\Entities\Blob;

/**
 * @extends Repository<Blob>
 */
final class BlobRepository extends Repository
{
    protected string $dbConfigClassName = MainDbConfig::class;
    protected string $entityClassName = Blob::class;
    public static string $table = 'blobs';
}
