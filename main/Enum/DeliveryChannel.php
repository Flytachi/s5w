<?php

declare(strict_types=1);

namespace Main\Enum;

enum DeliveryChannel: string
{
    case PUBLIC = 'public';
    case PRIVATE = 'private';
    case TEMPORARY = 'temporary';
}
