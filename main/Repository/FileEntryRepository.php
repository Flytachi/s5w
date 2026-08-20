<?php

declare(strict_types=1);

namespace Main\Repository;

use Flytachi\Winter\Ppa\Stereotype\Repository;
use Main\Configuration\MainDbConfig;
use Main\Entity\FileEntry;

/**
 * @extends Repository<FileEntry>
 */
final class FileEntryRepository extends Repository
{
    protected string $dbConfigClassName = MainDbConfig::class;
    protected string $entityClassName = FileEntry::class;
    public static string $table = 'files';
}
