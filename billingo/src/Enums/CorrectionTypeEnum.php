<?php

namespace App\Billingo\Enums;

enum CorrectionTypeEnum: string
{
    case ADVANCE = 'advance';
    case CANCELED = 'canceled';
    case CANCELLATION = 'cancellation';
    case CERT_OF_COMPLETION = 'cert_of_completion';
    case D_CERT_OF_COMPLETION = 'd_cert_of_completion';
    case DOSSIER = 'dossier';
    case DRAFT = 'draft';
    case DRAFT_OFFER = 'draft_offer';
    case DRAFT_ORDER_FORM = 'draft_order_form';
    case DRAFT_WAYBILL = 'draft_waybill';
    case INVOICE = 'invoice';
    case MODIFICATION = 'modification';
    case MODIFIED = 'modified';
    case OFFER = 'offer';
    case ORDER_FORM = 'order_form';
    case PROFORMA = 'proforma';
    case RECEIPT = 'receipt';
    case RECEIPT_CANCELLATION = 'receipt_cancellation';
    case WAYBILL = 'waybill';
}
