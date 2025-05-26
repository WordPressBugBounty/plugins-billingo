<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Validation\Validator;

class DocumentItemDataValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric'],
            'comment' => ['optional', 'string'],
            'is_generate_erase_code' => ['optional', 'boolean'],
        ];
    }
}
