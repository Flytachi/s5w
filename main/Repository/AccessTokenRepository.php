<?php

declare(strict_types=1);

namespace Main\Repository;

use Flytachi\Winter\Kernel\Ppa\Stereotype\Repository;
use Main\Config\MainDbConfig;
use Main\Entity\AccessToken;

/**
 * @extends Repository<AccessToken>
 */
final class AccessTokenRepository extends Repository
{
    protected string $dbConfigClassName = MainDbConfig::class;
    protected string $entityClassName = AccessToken::class;
    public static string $table = 'access_tokens';
}
