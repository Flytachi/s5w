<?php

declare(strict_types=1);

namespace Main\Repositories;

use Flytachi\Winter\Ppa\Stereotype\Repository;
use Main\Configuration\MainDbConfig;
use Main\Entities\Bucket;

/**
 * @extends Repository<Bucket>
 */
final class BucketRepository extends Repository
{
    protected string $dbConfigClassName = MainDbConfig::class;
    protected string $entityClassName = Bucket::class;
    public static string $table = 'buckets';
}
