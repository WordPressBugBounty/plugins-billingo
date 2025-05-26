<?php

namespace App\Billingo\Validation;

use App\Billingo\Enums\DiscountTypeEnum;

class DiscountValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'type' => ['optional', ['in', getEnumValues(DiscountTypeEnum::class)]],
            'value' => ['optional', 'numeric']
        ];
    }
}
