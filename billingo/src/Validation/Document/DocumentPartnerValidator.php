<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Enums\Partner\TaxTypeEnum;
use App\Billingo\Models\Address;
use App\Billingo\Models\Partner\PartnerShipping;
use App\Billingo\Validation\Validator;

class DocumentPartnerValidator extends Validator
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
            'tax_type' => ['optional', ['in', getEnumValues(TaxTypeEnum::class)]],
            'partner_shipping' => ['optional', ['instanceOf', PartnerShipping::class]]
        ];
    }
}
