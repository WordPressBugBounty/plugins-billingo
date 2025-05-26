<?php

namespace App\Billingo\Validation\Spending;

use App\Billingo\Enums\CurrencyEnum;
use App\Billingo\Enums\PaymentMethodEnum;
use App\Billingo\Enums\Spending\CategoryEnum;
use App\Billingo\Models\Spending\SpendingPartner;
use App\Billingo\Validation\Validator;

class SpendingValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'id' => ['optional', 'integer'],
            'organization_id' => ['optional', 'integer'],
            'category' => ['optional', ['in', getEnumValues(CategoryEnum::class)]],
            'paid_at' => ['optional', ['dateFormat', 'Y-m-d']],
            'fulfillment_date' => ['optional', ['dateFormat', 'Y-m-d']],
            'partner' => ['optional', ['instanceOf', SpendingPartner::class]],
            'invoice_number' => ['optional', 'string'],
            'currency' => ['optional', ['in', getEnumValues(CurrencyEnum::class)]],
            'conversion_rate' => ['optional', 'numeric'],
            'total_gross' => ['optional', 'numeric'],
            'total_gross_local' => ['optional', 'numeric'],
            'total_vat_amount' => ['optional', 'numeric'],
            'total_vat_amount_local' => ['optional', 'numeric'],
            'invoice_date' => ['optional', ['dateFormat', 'Y-m-d']],
            'due_date' => ['optional', ['dateFormat', 'Y-m-d']],
            'payment_method' => ['optional', ['in', getEnumValues(PaymentMethodEnum::class)]],
            'comment' => ['optional', 'nullable', 'string'],
            'is_created_by_nav' => ['optional', 'boolean'],
        ];
    }
}
