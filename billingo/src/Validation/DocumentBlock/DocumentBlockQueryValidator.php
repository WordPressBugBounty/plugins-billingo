<?php

namespace App\Billingo\Validation\DocumentBlock;

use App\Billingo\Enums\DocumentBlockTypeEnum;
use App\Billingo\Validation\Validator;

class DocumentBlockQueryValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'page' => ['integer'],
            'per_page' => ['integer'],
            'type' => [['in', getEnumValues(DocumentBlockTypeEnum::class)]],
        ];
    }
}
