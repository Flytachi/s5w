<?php

declare(strict_types=1);

namespace Main\Repository;

use Flytachi\Winter\Kernel\Ppa\Stereotype\Repository;
use Main\Configuration\MainDbConfig;
use Main\Entity\Upload;

/**
 * @extends Repository<Upload>
 */
final class UploadRepository extends Repository
{
    protected string $dbConfigClassName = MainDbConfig::class;
    protected string $entityClassName = Upload::class;
    public static string $table = 'uploads';
}
