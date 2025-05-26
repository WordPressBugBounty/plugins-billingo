<?php

namespace App\Billingo\Validation\Partner;

use App\Billingo\Validation\Validator;

class PartnerGiroSettingsValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'giro_default_settings' => ['optional', 'boolean'],
            'giro_payment_request_enabled' => ['optional', 'boolean'],
            'giro_different_amount_allowed' => ['optional', 'boolean'],
        ];
    }
}
