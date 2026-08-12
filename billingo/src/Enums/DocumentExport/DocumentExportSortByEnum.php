<?php

namespace App\Billingo\Enums\DocumentExport;

enum DocumentExportSortByEnum: string
{
    case FULFILLMENT_DATE = 'fulfillment_date';
    case INVOICE_DATE = 'invoice_date';
    case INVOICE_RAW_NUMBER = 'invoice_raw_number';
}
