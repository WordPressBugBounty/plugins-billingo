<?php

namespace App\Billingo\Enums;

enum PaymentStatusEnum: string
{
    case EXPIRED = 'expired';
    case NONE = 'none';
    case OUTSTANDING = 'outstanding';
    case PAID = 'paid';
    case PARTIALLY_PAID = 'partially_paid';
}
