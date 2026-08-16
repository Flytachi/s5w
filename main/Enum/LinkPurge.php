<?php

declare(strict_types=1);

namespace Main\Enum;

/** Что вычищаем из списка временных ссылок. */
enum LinkPurge: string
{
    case REVOKED = 'revoked';
    case EXPIRED = 'expired';
}
