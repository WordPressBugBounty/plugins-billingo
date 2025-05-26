<?php

namespace App\Billingo\Validation\Organization;

use App\Billingo\Models\Subscription;
use App\Billingo\Validation\Validator;

class OrganizationDataValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'tax_code' => ['optional', 'string'],
            'subscription' => ['optional', ['instanceOf', Subscription::class]],
        ];
    }
}
