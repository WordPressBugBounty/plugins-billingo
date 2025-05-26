<?php

namespace App\Billingo\Validation\DocumentBlock;

use App\Billingo\Enums\DocumentBlockTypeEnum;
use App\Billingo\Validation\Validator;

class DocumentBlockValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'id' => ['optional', 'integer'],
            'name' => ['optional', 'string'],
            'prefix' => ['optional', 'string'],
            'custom_field1' => ['optional', 'string'],
            'custom_field2' => ['optional', 'string'],
            'type' => ['optional', 'string', ['in', getEnumValues(DocumentBlockTypeEnum::class)]],
        ];
    }
}
