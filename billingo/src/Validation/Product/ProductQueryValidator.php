<?php

namespace App\Billingo\Validation\Product;

use App\Billingo\Validation\Validator;

class ProductQueryValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'page' => ['optional', 'integer'],
            'per_page' => ['optional', 'integer'],
            'query' => ['optional', 'string'],
        ];
    }
}
