<?php

namespace App\Billingo\Validation\Partner;

use App\Billingo\Enums\Partner\ShowTypeEnum;
use App\Billingo\Enums\Partner\TaxTypeEnum;
use App\Billingo\Models\Address;
use App\Billingo\Models\Partner\PartnerCustomBillingoSettings;
use App\Billingo\Models\Partner\PartnerGiroSettings;
use App\Billingo\Models\Partner\PartnerShipping;
use App\Billingo\Validation\Validator;

class PartnerValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'id' => ['optional', 'integer'],
            'name' => ['required', 'string'],
            'address' => ['required', ['instanceOf', Address::class]],
            'emails' => ['optional', 'array'],
            'emails.*' => ['email'],
            'taxcode' => ['optional', 'string'],
            'iban' => ['optional', 'string'],
            'swift' => ['optional', 'string'],
            'account_number' => ['optional', 'string'],
            'phone' => ['optional', 'string'],
            'general_ledger_number' => ['optional', 'string'],
            'tax_type' => ['optional', ['in', getEnumValues(TaxTypeEnum::class)]],
            'custom_billing_settings' => ['optional', ['instanceOf', PartnerCustomBillingoSettings::class]],
            'group_member_tax_number' => ['optional', 'string'],
            'giro_settings' => ['optional', 'nullable', ['instanceOf', PartnerGiroSettings::class]],
            'partner_shipping' => ['optional', 'nullable', ['instanceOf', PartnerShipping::class]],
            'internal_comment' => ['optional', 'string'],
            'partner_show_type' => ['optional', 'nullable', ['in', getEnumValues(ShowTypeEnum::class)]],
        ];
    }
}
