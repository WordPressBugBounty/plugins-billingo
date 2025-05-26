<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Validation\Validator;

class OnlineSzamlaStatusMessageValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'validation_result_code' => ['optional', 'string'],
            'validation_error_code' => ['optional', 'string'],
            'human_readable_message' => ['optional', 'string'],
        ];
    }
}
