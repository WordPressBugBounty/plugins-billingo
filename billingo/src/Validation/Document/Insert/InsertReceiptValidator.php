<?php

namespace App\Billingo\Validation\Document\Insert;

use App\Billingo\Enums\CurrencyEnum;
use App\Billingo\Enums\Document\TypeEnum;
use App\Billingo\Enums\PaymentMethodEnum;
use App\Billingo\Models\Document\ReceiptItemData;
use App\Billingo\Models\Document\ReceiptProductData;
use App\Billingo\Validation\Validator;

class InsertReceiptValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'vendor_id' => ['optional', 'string'],
            'partner_id' => ['optional', 'integer'],
            'name' => ['optional', 'string'],
            'emails' => ['optional', 'array'],
            'emails.*' => ['email'],
            'block_id' => ['required', 'integer'],
            'type' => ['required', ['in', getEnumValues(TypeEnum::class)]],
            'payment_method' => ['required', ['in', getEnumValues(PaymentMethodEnum::class)]],
            'currency' => ['required', ['in', getEnumValues(CurrencyEnum::class)]],
            'conversion_rate' => ['optional', 'numeric'],
            'electronic' => ['optional', 'boolean'],
            'items' => ['optional', 'array'],
            'items.*' => ['optional', ['instanceOfAny', [ReceiptItemData::class, ReceiptProductData::class]]],
        ];
    }
}
