<?php

namespace App\Billingo\Enums;

enum invoiceNumberFormatEnum: string
{
    case DEFAULT = 'default';
    case SHORT_WITH_HYPHEN = 'short_with_hyphen';
}
