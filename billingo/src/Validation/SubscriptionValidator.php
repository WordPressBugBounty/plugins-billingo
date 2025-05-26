<?php

namespace App\Billingo\Validation;

use App\Billingo\Enums\FeatureEnum;

class SubscriptionValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'expiration_date' => ['optional', ['dateFormat', 'Y-m-d']],
            'features' => ['optional', 'array'],
            'features.*' => ['optional', ['in', getEnumValues(FeatureEnum::class)]],
        ];
    }
}
