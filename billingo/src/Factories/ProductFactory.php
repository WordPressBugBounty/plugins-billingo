<?php

namespace App\Billingo\Factories;

use App\Billingo\Enums\CurrencyEnum;
use App\Billingo\Enums\EntitlementEnum;
use App\Billingo\Enums\VatEnum;

class ProductFactory extends BaseFactory
{

    public function definition(): array
    {
        $netUnitPrice = $this->faker->randomFloat(2, 1, 1000);

        return [
            'id' => $this->faker->optional()->randomNumber(),
            'name' => $this->faker->word(),
            'comment' => $this->faker->optional()->sentence(),
            'currency' => $this->faker->randomElement(getEnumValues(CurrencyEnum::class)),
            'vat' => $this->faker->randomElement(getEnumValues(VatEnum::class)),
            'net_unit_price' => $netUnitPrice,
            'gross_unit_price' => $this->faker->optional(0.1)->randomFloat(2, 1, 1000),
            'unit' => $this->faker->word(),
            'general_ledger_number' => $this->faker->optional()->regexify('[A-Z]{4}-[0-9]{6}'),
            'general_ledger_taxcode' => $this->faker->optional()->regexify('[A-Z]{4}-[0-9]{6}'),
            'entitlement' => $this->faker->optional()->randomElement(getEnumValues(EntitlementEnum::class)),
            'ean' => $this->faker->optional()->ean13(),
            'sku' => $this->faker->optional()->regexify('[A-Z]{5}-[0-9]{5}'),
            'is_manage' => $this->faker->optional()->boolean(),
            'purchase_price' => $this->faker->optional()->randomFloat(2, 1, 1000),
        ];
    }
}
