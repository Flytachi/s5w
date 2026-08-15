<?php

declare(strict_types=1);

namespace Main\Repository;

use Flytachi\Winter\Kernel\Ppa\Stereotype\Repository;
use Main\Config\MainDbConfig;
use Main\Entity\Bucket;

/**
 * @extends Repository<Bucket>
 */
final class BucketRepository extends Repository
{
    protected string $dbConfigClassName = MainDbConfig::class;
    protected string $entityClassName = Bucket::class;
    public static string $table = 'buckets';
}
