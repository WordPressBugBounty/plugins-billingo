<?php

namespace App\Billingo\Validation\Currency;

use App\Billingo\Enums\CurrencyEnum;
use App\Billingo\Enums\CurrencyQueryEnum;
use App\Billingo\Validation\Validator;

class ConversationRateValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'from_currency' => ['required', ['in', getEnumValues(CurrencyQueryEnum::class)]],
            'to_currency' => ['required', ['in', getEnumValues(CurrencyQueryEnum::class)]],
            'conversation_rate' => ['optional', 'numeric'],
            'date' => ['optional', ['dateFormat', 'Y-m-d']],
        ];
    }
}
