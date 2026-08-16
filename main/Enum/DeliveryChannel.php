<?php

declare(strict_types=1);

namespace Main\Enum;

enum DeliveryChannel
{
    case PUBLIC;   // /o — без авторизации
    case PRIVATE;  // /p — по токену бакета
    case TEMPORARY; // /t — по подписанной ссылке
}
