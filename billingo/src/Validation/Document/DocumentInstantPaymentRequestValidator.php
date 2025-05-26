<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Validation\Validator;

class DocumentInstantPaymentRequestValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'debtor_bank_account' => ['optional', 'string'],
            'different_amount_allowed' => ['optional', 'boolean'],
            'update_partner_payment_details' => ['optional', 'boolean'],
        ];
    }
}
