<?php

namespace App\Billingo\Factories;

use App\Billingo\Enums\EntitlementEnum;
use App\Billingo\Enums\UnitPriceTypeEnum;
use App\Billingo\Enums\VatEnum;

class DocumentProductDataFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'unit_price' => $this->faker->randomFloat(2, 1, 1000),
            'unit_price_type' => $this->faker->randomElement(getEnumValues(UnitPriceTypeEnum::class)),
            'quantity' => $this->faker->randomFloat(2, 1, 100),
            'unit' => $this->faker->word(),
            'vat' => $this->faker->randomElement(getEnumValues(VatEnum::class)),
            'comment' => $this->faker->optional()->sentence(),
            'entitlement' => $this->faker->optional()->randomElement(getEnumValues(EntitlementEnum::class)),
            'sku' => $this->faker->optional()->bothify('SKU-####'),
            'is_generate_erase_code' => $this->faker->boolean(),
        ];
    }
}
