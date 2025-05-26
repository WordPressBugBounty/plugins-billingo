<?php

namespace App\Billingo\Validation\Spending;

use App\Billingo\Models\Address;
use App\Billingo\Validation\Validator;

class SpendingPartnerValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'id' => ['optional', 'integer'],
            'name' => ['optional', 'string'],
            'tax_code' => ['optional', 'string'],
            'address' => ['optional', ['instanceOf', Address::class]],
            'iban' => ['optional', 'string'],
            'swift' => ['optional', 'string'],
            'account_number' => ['optional', 'string'],
            'phone' => ['optional', 'string'],
            'internal_comment' => ['optional', 'string'],
            'group_member_tax_number' => ['optional', 'string'],
        ];
    }
}
