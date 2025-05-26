<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Enums\Document\FormatEnum;
use App\Billingo\Enums\Document\TypeEnum;
use App\Billingo\Validation\Validator;

class InvoiceSettingsValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'document_type' => ['optional', ['in', getEnumValues(TypeEnum::class)]],
            'fulfillment_date' => ['optional', ['dateFormat', 'Y-m-d']],
            'due_date' => ['optional', ['dateFormat', 'Y-m-d']],
            'document_format' => ['optional', ['in', getEnumValues(FormatEnum::class)]],
            'comment' => ['optional', 'string'],
        ];
    }
}
