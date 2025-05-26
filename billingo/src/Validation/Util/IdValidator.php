<?php

namespace App\Billingo\Validation\Util;

use App\Billingo\Validation\Validator;

class IdValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'id' => ['optional', 'integer'],
            'legacy_id' => ['optional', 'integer'],
        ];
    }
}