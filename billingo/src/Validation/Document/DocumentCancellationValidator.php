<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Validation\Validator;

class DocumentCancellationValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'cancellation_reason' => ['optional', 'string'],
            'cancellation_recipients' => ['optional', 'string'],
        ];
    }
}
