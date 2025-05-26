<?php

namespace App\Billingo\Enums\Partner;

enum TaxTypeEnum: string
{
    case NULL = '';
    case FOREIGN = 'FOREIGN';
    case HAS_TAX_NUMBER = 'HAS_TAX_NUMBER';
    case NO_TAX_NUMBER = 'NO_TAX_NUMBER';
}
