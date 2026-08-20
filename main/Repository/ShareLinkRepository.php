<?php

declare(strict_types=1);

namespace Main\Repository;

use Flytachi\Winter\Ppa\Stereotype\Repository;
use Main\Configuration\MainDbConfig;
use Main\Entity\ShareLink;

/**
 * @extends Repository<ShareLink>
 */
final class ShareLinkRepository extends Repository
{
    protected string $dbConfigClassName = MainDbConfig::class;
    protected string $entityClassName = ShareLink::class;
    public static string $table = 'share_links';
}
