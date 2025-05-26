<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Enums\Document\TypeEnum;
use App\Billingo\Enums\DontSendToNavReasonEnum;
use App\Billingo\Enums\OnlinePaymentEnum;
use App\Billingo\Enums\RoundEnum;
use App\Billingo\Models\Document\DocumentInstantPaymentRequest;
use App\Billingo\Validation\Validator;

class DocumentSettingsValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'mediated_service' => ['optional', 'boolean'],
            'without_financial_fulfillment' => ['optional', 'boolean'],
            'online_payment' => ['optional', ['in', getEnumValues(OnlinePaymentEnum::class)]],
            'should_send_email' => ['optional', 'boolean'],
            'round' => ['optional', ['in', getEnumValues(RoundEnum::class)]],
            'dont_send_to_nav_reason' => ['optional', ['in', getEnumValues(DontSendToNavReasonEnum::class)]],
            'order_number' => ['optional', 'string'],
            'place_id' => ['optional', 'integer'],
            'instant_payment' => ['optional', 'boolean'],
            'selected_type' => ['optional', ['in', getEnumValues(TypeEnum::class)]],
            'instant_payment_request' => ['optional', ['instanceOf', DocumentInstantPaymentRequest::class]]
        ];
    }
}
