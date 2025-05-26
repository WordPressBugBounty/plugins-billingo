<?php

namespace App\Billingo\Factories;

use App\Billingo\Enums\Partner\ShowTypeEnum;
use App\Billingo\Enums\Partner\TaxTypeEnum;
use App\Billingo\Models\Address;
use App\Billingo\Models\Partner\PartnerCustomBillingoSettings;
use App\Billingo\Models\Partner\PartnerGiroSettings;
use App\Billingo\Models\Partner\PartnerShipping;

class PartnerFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'id' => $this->faker->numberBetween(1, 1000),
            'name' => $this->faker->company(),
            'address' => Address::factory(),
            'emails' => $this->faker->boolean() ? [$this->faker->email(), $this->faker->email()] : null,
            'taxcode' => $this->faker->optional()->regexify('[0-9]{8}-[0-9]{1}-[0-9]{2}'),
            'iban' => $this->faker->optional()->iban(),
            'swift' => $this->faker->optional()->swiftBicNumber(),
            'account_number' => $this->faker->optional()->regexify('[A-Z]{2}--[0-9]{6}'),
            'phone' => $this->faker->optional()->phoneNumber(),
            'general_ledger_number' => $this->faker->optional()->regexify('[0-9]{5}'),
            'tax_type' => $this->faker->optional()->randomElement(getEnumValues(TaxTypeEnum::class)),
            'custom_billing_settings' => $this->faker->boolean() ? PartnerCustomBillingoSettings::factory() : null,
            'group_member_tax_number' => $this->faker->optional()->regexify('[0-9]{8}'),
            'giro_settings' => $this->faker->boolean() ? PartnerGiroSettings::factory() : null,
            'partner_shipping' => $this->faker->boolean() ? PartnerShipping::factory() : null,
            'internal_comment' => $this->faker->optional()->sentence(),
            'partner_show_type' => $this->faker->optional()->randomElement(getEnumValues(ShowTypeEnum::class)),
        ];
    }
}
