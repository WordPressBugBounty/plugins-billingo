<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Enums\VatEnum;
use App\Billingo\Validation\Validator;

class ReceiptProductValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'name' => ['optional', 'string'],
            'unit_price' => ['required', 'numeric'],
            'vat' => ['required', ['in', getEnumValues(VatEnum::class)]],
        ];
    }
}
