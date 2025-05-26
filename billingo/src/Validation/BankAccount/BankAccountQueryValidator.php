<?php

namespace App\Billingo\Validation\BankAccount;

use App\Billingo\Validation\Validator;

class BankAccountQueryValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'page' => ['optional', 'integer'],
            'per_page' => ['optional', 'integer'],
        ];
    }
}
