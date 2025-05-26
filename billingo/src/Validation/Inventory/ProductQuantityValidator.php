<?php

namespace App\Billingo\Validation\Inventory;

use App\Billingo\Validation\Validator;

class ProductQuantityValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'available_quantity' => ['optional', 'numeric'],
        ];
    }
}
