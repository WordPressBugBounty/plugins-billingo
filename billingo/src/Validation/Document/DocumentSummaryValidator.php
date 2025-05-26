<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Models\Document\DocumentVatRateSummary;
use App\Billingo\Validation\Validator;

class DocumentSummaryValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'net_amount' => ['optional', 'numeric'],
            'net_amount_local' => ['optional', 'numeric'],
            'gross_amount_local' => ['optional', 'numeric'],
            'vat_amount' => ['optional', 'numeric'],
            'vat_amount_local' => ['optional', 'numeric'],
            'vat_rate_summary' => ['optional', 'array'],
            'vat_rate_summary.*' => [['instanceOf', DocumentVatRateSummary::class]],
        ];
    }
}
