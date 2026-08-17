<?php

declare(strict_types=1);

namespace Main\Enum;

enum LinkPurge: string
{
    case REVOKED = 'revoked';
    case EXPIRED = 'expired';
}
