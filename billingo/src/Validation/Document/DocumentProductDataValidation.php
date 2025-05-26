<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Enums\EntitlementEnum;
use App\Billingo\Enums\UnitPriceTypeEnum;
use App\Billingo\Enums\VatEnum;
use App\Billingo\Validation\Validator;

class DocumentProductDataValidation extends Validator
{

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'unit_price' => ['required', 'numeric'],
            'unit_price_type' => ['required', ['in', getEnumValues(UnitPriceTypeEnum::class)]],
            'quantity' => ['required', 'numeric'],
            'unit' => ['required', 'string'],
            'vat' => ['required', ['in', getEnumValues(VatEnum::class)]],
            'comment' => ['optional', 'string'],
            'entitlement' => ['optional', ['in', getEnumValues(EntitlementEnum::class)]],
            'sku' => ['optional', 'string'],
            'is_generate_erase_code' => ['optional', 'boolean'],
        ];
    }
}
