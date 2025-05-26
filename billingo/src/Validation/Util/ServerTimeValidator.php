<?php

namespace App\Billingo\Validation\Util;

use App\Billingo\Validation\Validator;

class ServerTimeValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'epoch' => ['optional', 'integer'],
            'formatted' => ['optional', 'string'],
            'w3c' => ['optional', 'string'],
            'timezone' => ['optional', 'string'],
        ];
    }
}
