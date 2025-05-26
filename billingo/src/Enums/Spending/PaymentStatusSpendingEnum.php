<?php

namespace App\Billingo\Enums\Spending;

enum PaymentStatusSpendingEnum: string
{
    case ALL = 'all';
    case PAID = 'paid';
    case UNPAID = 'unpaid ';
}
