<?php

namespace App\Billingo\Validation;

use App\Billingo\Enums\PaymentMethodEnum;
use App\Billingo\Validation\Validator;

class PaymentHistoryValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'date' => ['required', ['dateFormat', 'Y-m-d']],
            'price' => ['required', 'numeric'],
            'payment_method' => ['required', ['in', getEnumValues(PaymentMethodEnum::class)]],
            'voucher_number' => ['optional', 'string'],
            'conversion_rate' => ['optional', 'numeric'],
        ];
    }
}
