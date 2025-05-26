<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Enums\PaymentMethodEnum;
use App\Billingo\Models\Document\DocumentItem;
use App\Billingo\Models\Document\DocumentItemData;
use App\Billingo\Models\Document\DocumentProductData;
use App\Billingo\Models\Document\ReceiptItemData;
use App\Billingo\Validation\Validator;

class ModificationDocumentInsertValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'due_date' => ['optional', ['regex', '/^\d{4}-\d{2}-\d{2}$/']],
            'comment' => ['optional', 'string'],
            'payment_method' => ['optional', ['in', getEnumValues(PaymentMethodEnum::class)]],
            'without_financial_fulfillment' => ['optional', 'boolean'],
            'items' => ['optional', 'array'],
            'items.*' => ['optional', ['instanceOfAny', [DocumentItemData::class, DocumentProductData::class]]]
        ];
    }
}
