<?php

namespace App\Billingo\Enums;

enum ShippingModeEnum: string
{
    case HOME_DELIVERY = 'home_delivery';
    case NONE = 'none';
    case PERSONAL_DELIVERY = 'personal_delivery ';
}
