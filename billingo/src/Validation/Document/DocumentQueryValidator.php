<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Enums\PaymentMethodEnum;
use App\Billingo\Enums\PaymentStatusEnum;
use App\Billingo\Validation\Validator;

class DocumentQueryValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'page' => ['integer'],
            'per_page' => ['integer'],
            'block_id' => ['integer'],
            'partner_id' => ['integer'],
            'payment_method' => [['in', getEnumValues(PaymentMethodEnum::class)]],
            'payment_status' => [['in', getEnumValues(PaymentStatusEnum::class)]],
            'start_date' => [['dateFormat', 'Y-m-d']],
            'end_date' => [['dateFormat', 'Y-m-d']],
            'start_number' => ['integer'],
            'end_number' => ['integer'],
            'start_year' => ['integer'],
            'end_year' => ['integer'],
            'type' => ['string'],
            'query' => ['string'],
            'paid_start_date' => [['dateFormat', 'Y-m-d']],
            'paid_end_date' => [['dateFormat', 'Y-m-d']],
            'fulfillment_start_date' => [['dateFormat', 'Y-m-d']],
            'fulfillment_end_date' => [['dateFormat', 'Y-m-d']],
            'last_modified_date' => [['dateFormat', 'Y-m-d']],
        ];
    }
}
