<?php

declare(strict_types=1);

namespace Main\Http;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\Cdo\Connection\CDOException;
use Flytachi\Winter\Kernel\ConnectionPool\PoolException;
use Flytachi\Winter\Kernel\Http\Response\AdviceException;
use Flytachi\Winter\Kernel\Http\Stereotype\ExceptionResponseBase;
use Flytachi\Winter\Kernel\Ppa\Entity\EntityException;
use Flytachi\Winter\Kernel\Ppa\Pool\PpaPoolException;
use Flytachi\Winter\Kernel\Ppa\Repository\RepositoryException;

#[AdviceException(
    \PDOException::class,
    CDOException::class,
    RepositoryException::class,
    EntityException::class,
    PoolException::class,
    PpaPoolException::class,
)]
final class DatabaseErrorResponse extends ExceptionResponseBase
{
    private const string MESSAGE = 'The server could not complete the request, try again later';

    public function __construct(\Throwable $throwable)
    {
        parent::__construct(
            env('DEBUG', false)
                ? $throwable
                : new \RuntimeException(self::MESSAGE, HttpCode::INTERNAL_SERVER_ERROR->value),
        );
    }
}
