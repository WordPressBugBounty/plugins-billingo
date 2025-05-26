<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Models\Document\OnlineSzamlaStatusMessage;
use App\Billingo\Validation\Validator;

class OnlineSzamlaStatusValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'transaction_id' => ['optional', 'string'],
            'status' => ['optional', 'string'],
            'messages' => ['optional', 'array'],
            'messages.*' => [['instanceOf', OnlineSzamlaStatusMessage::class]],
        ];
    }
}
