<?php

namespace App\Billingo\Validation\DocumentExport;

use App\Billingo\Enums\DocumentExport\DocumentExportStatusStateEnum;
use App\Billingo\Validation\Validator;

class DocumentExportStatusStateValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'id' => ['optional', 'string'],
            'state' => ['optional', ['in', getEnumValues(DocumentExportStatusStateEnum::class)]],
            'message' => ['optional', 'string'],
        ];
    }
}
