<?php

namespace App\Billingo\Validation\DocumentExport;

use App\Billingo\Validation\Validator;

class LedgerNumberInformationValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'bevetel' => ['optional', 'string'],
            'vevo' => ['optional', 'string'],
            'penztar' => ['optional', 'string'],
            'afa' => ['optional', 'string'],
        ];
    }
}
