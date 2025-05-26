<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Validation\Validator;

class AncestorValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'id' => ['optional', 'integer'],
            'invoice_number' => ['optional', 'string', ['regex', '/^\d{4}-\d{6}$/']],
        ];
    }
}
