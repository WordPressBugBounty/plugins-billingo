<?php

namespace App\Billingo\Enums;

enum DocumentBlockTypeEnum: string
{
    case CERTIFICATE_OF_COMPLETION = 'certificate_of_completion';
    case DOSSIER = 'dossier';
    case INVOICE = 'invoice';
    case OFFER = 'offer';
    case ORDER_FORM = 'order_form';
    case RECEIPT = 'receipt';
    case WAYBILL = 'waybill';
}
