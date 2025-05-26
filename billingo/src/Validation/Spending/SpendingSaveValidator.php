<?php

namespace App\Billingo\Validation\Spending;

use App\Billingo\Enums\CurrencyEnum;
use App\Billingo\Enums\Spending\CategoryEnum;
use App\Billingo\Enums\Spending\SpendingPaymentMethodEnum;
use App\Billingo\Validation\Validator;

class SpendingSaveValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'currency' => ['required', ['in', getEnumValues(CurrencyEnum::class)]],
            'conversion_rate' => ['optional', 'numeric'],
            'total_gross' => ['required', 'numeric'],
            'total_gross_huf' => ['required', 'numeric'],
            'total_vat_amount' => ['required', 'numeric'],
            'total_vat_amount_huf' => ['required', 'numeric'],
            'fulfillment_date' => ['required', ['dateFormat', 'Y-m-d']],
            'paid_at' => ['optional', ['dateFormat', 'Y-m-d']],
            'category' => ['required', ['in', getEnumValues(CategoryEnum::class)]],
            'comment' => ['optional', 'string'],
            'invoice_number' => ['optional', 'string'],
            'invoice_date' => ['optional', ['dateFormat', 'Y-m-d']],
            'due_date' => ['optional', ['dateFormat', 'Y-m-d']],
            'payment_method' => ['required', ['in', getEnumValues(SpendingPaymentMethodEnum::class)]],
            'partner_id' => ['optional', 'integer'],
        ];
    }
}
