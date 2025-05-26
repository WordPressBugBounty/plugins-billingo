<?php

namespace App\Billingo\Enums\DocumentExport;

enum DocumentExportOtherOptionsEnum: string
{
    case ALL = 'all';
    case EXPIRED = 'expired';
    case OUTSTANDING = 'outstanding';
}
