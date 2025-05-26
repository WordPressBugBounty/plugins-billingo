<?php

namespace App\Billingo\Validation\Partner;

use App\Billingo\Enums\ShippingModeEnum;
use App\Billingo\Models\Address;
use App\Billingo\Validation\Validator;

class PartnerShippingValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'match' => ['optional', 'boolean'],
            'name' => ['optional', 'string'],
            'mode' => ['optional', ['in', getEnumValues(ShippingModeEnum::class)]],
            'address' => ['optional', ['instanceOf', Address::class]]
        ];
    }
}
