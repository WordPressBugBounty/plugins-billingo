<?php

namespace App\Billingo\Validation\Document;


use App\Billingo\Enums\CurrencyEnum;
use App\Billingo\Enums\Document\LanguageEnum;
use App\Billingo\Enums\Document\TypeEnum;
use App\Billingo\Enums\PaymentMethodEnum;
use App\Billingo\Models\Discount;
use App\Billingo\Models\Document\DocumentProductData;
use App\Billingo\Models\Document\DocumentInstantPaymentRequest;
use App\Billingo\Models\Document\DocumentItem;
use App\Billingo\Models\Document\DocumentSettings;
use App\Billingo\Validation\Validator;

class DocumentInsertValidator extends Validator
{
    protected function rules(): array
    {
        return [
            'vendor_id' => ['optional', 'string'],
            'partner_id' => ['required', 'integer'],
            'block_id' => ['required', 'integer'],
            'bank_account_id' => ['optional', 'integer'],
            'type' => ['required', ['in', getEnumValues(TypeEnum::class)]],
            'fulfillment_date' => ['required', ['dateFormat', 'Y-m-d']],
            'due_date' => ['required', ['dateFormat', 'Y-m-d']],
            'payment_method' => ['required', ['in', getEnumValues(PaymentMethodEnum::class)]],
            'language' => ['required', ['in', getEnumValues(LanguageEnum::class)]],
            'currency' => ['required', ['in', getEnumValues(CurrencyEnum::class)]],
            'conversion_rate' => ['optional', 'numeric'],
            'electronic' => ['optional', 'boolean'],
            'paid' => ['optional', 'boolean'],
            'items' => ['required', 'array'],
            'items.*' => ['optional', ['instanceOfAny', [DocumentItem::class, DocumentProductData::class]]],
            'comment' => ['optional', 'string'],
            'settings' => ['optional', ['instanceOf', DocumentSettings::class]],
            'advance_invoice' => ['optional', 'array'],
            'advance_invoice.*' => ['integer'],
            'discount' => ['optional', ['instanceOf', Discount::class]],
            'instant_payment' => ['optional', 'boolean'],
            'instant_payment_request' => ['nullable', ['instanceOf', DocumentInstantPaymentRequest::class]],
        ];
    }
}
