<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Validation\Validator;

class DocumentVatRateSummaryValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'vat_name' => ['optional', 'string'],
            'vat_percentage' => ['optional', 'numeric'],
            'vat_rate_net_amount' => ['optional', 'numeric'],
            'vat_rate_vat_amount' => ['optional', 'numeric'],
            'vat_rate_vat_amount_local' => ['optional', 'numeric'],
            'vat_rate_gross_amount' => ['optional', 'numeric'],
        ];
    }
}
