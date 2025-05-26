<?php

namespace App\Billingo\Validation\Partner;

use App\Billingo\Enums\CurrencyEnum;
use App\Billingo\Enums\Document\FormatEnum;
use App\Billingo\Enums\Document\LanguageEnum;
use App\Billingo\Enums\PaymentMethodEnum;
use App\Billingo\Models\Discount;
use App\Billingo\Validation\Validator;

class PartnerCustomBillingSettingsValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'payment_method' => ['nullable', ['in', getEnumValues(PaymentMethodEnum::class)]],
            'document_form' => ['nullable', ['in', getEnumValues(FormatEnum::class)]],
            'due_days' => ['nullable', 'integer'],
            'document_currency' => ['nullable', ['in', getEnumValues(CurrencyEnum::class)]],
            'template_language_code' => ['nullable', ['in', getEnumValues(LanguageEnum::class)]],
            'discount' => ['nullable', ['instanceOf', Discount::class]]
        ];
    }
}
