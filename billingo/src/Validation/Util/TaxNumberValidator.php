<?php

namespace App\Billingo\Validation\Util;

use App\Billingo\Enums\CheckTaxNumberMessageEnum;
use App\Billingo\Validation\Validator;

class TaxNumberValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'tax_number' => ['optional', ['regex', '/^[0-9]{8}-[1-5]-[0-9]{2}$/']],
            'result' => ['optional', ['in', getEnumValues(CheckTaxNumberMessageEnum::class)]],
        ];
    }
}
