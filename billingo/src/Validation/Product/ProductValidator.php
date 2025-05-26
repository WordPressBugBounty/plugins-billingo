<?php

namespace App\Billingo\Validation\Product;

use App\Billingo\Enums\CurrencyEnum;
use App\Billingo\Enums\EntitlementEnum;
use App\Billingo\Enums\VatEnum;
use App\Billingo\Validation\Validator;

class ProductValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'id' => ['optional', 'integer'],
            'name' => ['required', 'string'],
            'comment' => ['optional', 'string'],
            'currency' => ['required', ['in', getEnumValues(CurrencyEnum::class)]],
            'vat' => ['required', ['in', getEnumValues(VatEnum::class)]],
            'net_unit_price' => ['optional', 'numeric'],
            'gross_unit_price' => ['optional', 'numeric'],
            'unit' => ['required', 'string'],
            'general_ledger_number' => ['optional', 'string'],
            'general_ledger_taxcode' => ['optional', 'string'],
            'entitlement' => ['optional', ['in', getEnumValues(EntitlementEnum::class)]],
            'ean' => ['optional', 'string'],
            'sku' => ['optional', 'string'],
            'is_manage' => ['optional', 'boolean'],
            'purchase_price' => ['optional', 'numeric'],
            'is_generate_erase_code' => ['optional', 'boolean'],
        ];
    }
}
