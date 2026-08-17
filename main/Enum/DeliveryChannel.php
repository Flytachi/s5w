<?php

declare(strict_types=1);

namespace Main\Enum;

enum DeliveryChannel
{
    case PUBLIC;
    case PRIVATE;
    case TEMPORARY;
}
