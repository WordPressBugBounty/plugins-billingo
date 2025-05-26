<?php

namespace App\Billingo\Validation\DocumentBlock;

use App\Billingo\Enums\DocumentBlockTypeEnum;
use App\Billingo\Enums\FinancialFulfillmentHighlightEnum;
use App\Billingo\Enums\invoiceNumberFormatEnum;
use App\Billingo\Validation\Validator;

class DocumentBlockSchemaValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'prefix' => ['required', 'string'],
            'type' => ['required', ['in', getEnumValues(DocumentBlockTypeEnum::class)]],
            'invoice_number_format' => ['optional', ['in', getEnumValues(InvoiceNumberFormatEnum::class)]],
            'sender_email' => ['optional', 'nullable', 'email'],
            'sender_name' => ['optional', 'string'],
            'bcc_email' => ['optional', 'nullable', 'email'],
            'create_invoice_from_paid_proforma' => ['optional', 'boolean'],
            'fulfillment_date_from_proforma_payment_date' => ['optional', 'boolean'],
            'attach_xml_to_invoice_pdf' => ['optional', 'boolean'],
            'advance_paid_instant' => ['optional', 'boolean'],
            'financial_fulfillment_highlight' => [
                'optional',
                ['in', getEnumValues(FinancialFulfillmentHighlightEnum::class)]
            ],
            'entitlements_in_document_comment' => ['optional', 'boolean'],
        ];
    }
}
