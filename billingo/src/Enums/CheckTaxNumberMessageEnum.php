<?php

namespace App\Billingo\Enums;

enum CheckTaxNumberMessageEnum: string
{
    case EXTERNAL_NAV_SERVICE_UNREACHABLE = 'external_nav_service_unreachable';
    case INVALID_TAX_NUMBER = 'invalid_tax_number';
    case NO_ONLINE_SZAMLA_SETTINGS = 'no_online_szamla_settings';
    case NON_EXIST_TAX_NUMBER = 'non_exist_tax_number';
    case VALIDATION_OK = 'validation_ok';
}
