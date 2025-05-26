<?php

namespace App\Billingo\Validation;

class SendDocumentValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'emails' => ['array'],
            'emails.*' => ['email'],
        ];
    }
}
