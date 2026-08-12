<?php

namespace App\Billingo\Validation;

use App\Billingo\Enums\CountryEnum;

class AddressValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'country_code' => [
                'required',
                'string',
                ['in', getEnumValues(CountryEnum::class)],
            ],
            'post_code' => ['required', 'string'],
            'city' => ['required', 'string'],
            'address' => ['required', 'string'],
        ];
    }
}
