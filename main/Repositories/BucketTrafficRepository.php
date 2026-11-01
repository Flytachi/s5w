<?php

declare(strict_types=1);

namespace Main\Repositories;

use Flytachi\Winter\Ppa\Stereotype\Repository;
use Main\Configuration\MainDbConfig;
use Main\Entities\BucketTraffic;

/**
 * @extends Repository<BucketTraffic>
 */
final class BucketTrafficRepository extends Repository
{
    protected string $dbConfigClassName = MainDbConfig::class;
    protected string $entityClassName = BucketTraffic::class;
    public static string $table = 'bucket_traffic';
}
