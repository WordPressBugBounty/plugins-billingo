<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Enums\EntitlementEnum;
use App\Billingo\Enums\VatEnum;
use App\Billingo\Validation\Validator;

class DocumentItemValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'product_id' => ['optional', 'integer'],
            'name' => ['optional', 'string'],
            'net_unit_amount' => ['optional', 'numeric'],
            'quantity' => ['optional', 'numeric'],
            'unit' => ['optional', 'string'],
            'unit_price' => ['optional'],
            'unit_price_type' => ['optional'],
            'net_amount' => ['optional', 'numeric'],
            'gross_amount' => ['optional', 'numeric'],
            'vat' => ['optional', ['in', getEnumValues(VatEnum::class)]],
            'vat_amount' => ['optional', 'numeric'],
            'entitlement' => ['optional', ['in', getEnumValues(EntitlementEnum::class)]],
            'comment' => ['optional', 'string'],
            'sku' => ['optional', 'string'],
            'data_eraser_codes' => ['optional'],
        ];
    }
}
