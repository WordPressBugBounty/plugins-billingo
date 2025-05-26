<?php

namespace App\Billingo\Validation\DocumentExport;

use App\Billingo\Validation\Validator;

class DocumentExportIdValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'id' => ['required', 'integer'],
        ];
    }
}
