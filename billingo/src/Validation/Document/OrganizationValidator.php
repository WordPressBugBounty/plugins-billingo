<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Models\Address;
use App\Billingo\Models\Document\DocumentBankAccount;
use App\Billingo\Validation\Validator;

class OrganizationValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'name' => ['optional', 'string'],
            'tax_number' => ['optional', 'string'],
            'bank_account' => ['optional', ['instanceOf', DocumentBankAccount::class]],
            'address' => ['optional', ['instanceOf', Address::class]],
            'small_taxpayer' => ['optional', 'boolean'],
            'ev_number' => ['optional', 'string'],
            'eu_tax_number' => ['optional', 'string'],
            'cash_settled' => ['optional', 'boolean'],
        ];
    }
}
