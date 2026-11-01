<?php

declare(strict_types=1);

namespace Main\Repositories;

use Flytachi\Winter\Ppa\Stereotype\Repository;
use Main\Configuration\MainDbConfig;
use Main\Entities\ShareLink;

/**
 * @extends Repository<ShareLink>
 */
final class ShareLinkRepository extends Repository
{
    protected string $dbConfigClassName = MainDbConfig::class;
    protected string $entityClassName = ShareLink::class;
    public static string $table = 'share_links';
}
