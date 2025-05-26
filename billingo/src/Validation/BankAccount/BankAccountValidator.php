<?php

namespace App\Billingo\Validation\BankAccount;

use App\Billingo\Enums\CurrencyEnum;
use App\Billingo\Validation\Validator;

class BankAccountValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'id' => ['optional', 'integer'],
            'name' => ['required', 'string'],
            'account_number' => ['required', 'string'],
            'account_number_iban' => ['optional', 'string'],
            'swift' => ['optional', 'string'],
            'currency' => ['required', ['in', getEnumValues(CurrencyEnum::class)]],
        ];
    }
}
