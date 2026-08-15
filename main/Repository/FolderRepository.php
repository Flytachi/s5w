<?php

declare(strict_types=1);

namespace Main\Repository;

use Flytachi\Winter\Kernel\Ppa\Stereotype\Repository;
use Main\Config\MainDbConfig;
use Main\Entity\Folder;

/**
 * @extends Repository<Folder>
 */
final class FolderRepository extends Repository
{
    protected string $dbConfigClassName = MainDbConfig::class;
    protected string $entityClassName = Folder::class;
    public static string $table = 'folders';
}
