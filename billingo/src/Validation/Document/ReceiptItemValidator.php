<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Validation\Validator;

class ReceiptItemValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'product_id' => ['required', 'integer'],
        ];
    }
}
