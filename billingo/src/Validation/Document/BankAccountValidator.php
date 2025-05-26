<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Validation\Validator;

class BankAccountValidator extends  Validator
{
    public function rules(): array
    {
        return [
            'id' => ['optional', 'integer'],
            'name' => ['required', 'string'],
            'account_number' => ['required', 'string'],
            'account_number_iban' => ['optional', 'string'],
            'swift' => ['optional', 'string'],
        ];
    }
}
