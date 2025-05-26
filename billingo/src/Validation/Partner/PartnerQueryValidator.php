<?php

namespace App\Billingo\Validation\Partner;

use App\Billingo\Validation\Validator;

class PartnerQueryValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'page' => ['integer'],
            'per_page' => ['integer'],
            'query' => ['string'],
        ];
    }
}
