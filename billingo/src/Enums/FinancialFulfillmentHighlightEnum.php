<?php

namespace App\Billingo\Enums;

enum FinancialFulfillmentHighlightEnum: string
{
    case BASIC = 'basic';
    case HIGHLIGHTED = 'highlighted';
    case PAID_STAMP = 'paid_stamp';
}
