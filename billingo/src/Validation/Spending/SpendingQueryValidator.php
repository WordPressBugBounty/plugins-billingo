<?php

namespace App\Billingo\Validation\Spending;

use App\Billingo\Enums\CurrencyQueryEnum;
use App\Billingo\Enums\Spending\CategoryEnum;
use App\Billingo\Enums\Spending\PaymentStatusSpendingEnum;
use App\Billingo\Enums\Spending\SpendingPaymentMethodEnum;
use App\Billingo\Validation\Validator;

class SpendingQueryValidator extends Validator
{

    protected function rules(): array
    {
        $availableSpendingDate = ['due_date', 'fulfillment_date', 'invoice_date'];
        $availableSpendingType = ['all', 'manual', 'nav'];

        return [
            'q' => ['optional', 'string'],
            'page' => ['optional', 'integer'],
            'per_page' => ['optional', 'integer'],
            'spending_date' => ['optional', ['in', $availableSpendingDate]],
            'start_date' => ['optional', ['dateFormat', 'Y-m-d']],
            'end_date' => ['optional', ['dateFormat', 'Y-m-d']],
            'payment_status' => ['optional', ['in', getEnumValues(PaymentStatusSpendingEnum::class)]],
            'spending_type' => ['optional', ['in', $availableSpendingType]],
            'categories' => ['optional', ['in', getEnumValues(CategoryEnum::class)]],
            'currencies' => ['optional', ['in', getEnumValues(CurrencyQueryEnum::class)]],
            'payment_methods' => ['optional', ['in', getEnumValues(SpendingPaymentMethodEnum::class)]],
        ];
    }
}
