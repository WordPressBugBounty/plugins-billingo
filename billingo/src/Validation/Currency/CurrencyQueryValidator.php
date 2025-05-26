<?php

namespace App\Billingo\Validation\Currency;

use App\Billingo\Enums\CurrencyQueryEnum;
use App\Billingo\Validation\Validator;

class CurrencyQueryValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'from' => ['required', ['in', getEnumValues(CurrencyQueryEnum::class)]],
            'to' => ['required', ['in', getEnumValues(CurrencyQueryEnum::class)]],
        ];
    }
}
