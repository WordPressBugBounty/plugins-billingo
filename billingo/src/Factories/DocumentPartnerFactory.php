<?php

namespace App\Billingo\Factories;

use App\Billingo\Enums\Partner\TaxTypeEnum;
use App\Billingo\Models\Address;
use App\Billingo\Models\Partner\PartnerShipping;

class DocumentPartnerFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'id' => $this->faker->unique()->numberBetween(1, 1000),
            'name' => $this->faker->company(),
            'address' => Address::factory(),
            'emails' => [$this->faker->email(), $this->faker->email()],
            'tax_code' => $this->faker->regexify('[0-9]{8}-[0-9]{1}-[0-9]{2}'),
            'iban' => $this->faker->iban(),
            'swift' => $this->faker->swiftBicNumber(),
            'account_number' => $this->faker->regexify('[A-Z]{2}--[0-9]{6}'),
            'phone' => $this->faker->phoneNumber(),
            'tax_type' => $this->faker->randomElement(getEnumValues(TaxTypeEnum::class)),
            'partner_shipping' => PartnerShipping::factory(),
        ];
    }
}
