<?php

namespace App\Billingo\Enums\DocumentExport;

enum DocumentExportQueryTypeEnum: string
{
    case FULFILLMENT_DATE = 'fulfillment_date';
    case Invoice_DATE = 'invoice_date';
}
