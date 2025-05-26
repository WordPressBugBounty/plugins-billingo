<?php

namespace App\Billingo\Factories;

use App\Billingo\Enums\EntitlementEnum;
use App\Billingo\Enums\VatEnum;

class DocumentItemFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'product_id' => $this->faker->numberBetween(1, 1000),
            'name' => $this->faker->name(),
            'net_unit_amount' => $this->faker->randomFloat(2, 1, 1000),
            'quantity' => $this->faker->randomFloat(2, 1, 100),
            'unit' => $this->faker->randomElement(['pcs', 'kg', 'liter', 'meter', 'box']),
            'net_amount' => $this->faker->randomFloat(2, 1, 1000),
            'gross_amount' => $this->faker->randomFloat(2, 1, 1000),
            'vat' => $this->faker->randomElement(getEnumValues(VatEnum::class)),
            'vat_amount' => $this->faker->randomFloat(2, 1, 1000),
            'entitlement' => $this->faker->randomElement(getEnumValues(EntitlementEnum::class)),
            'comment' => $this->faker->sentence(),
            'sku' => $this->faker->bothify('SKU-####'),
            'is_generate_erase_code' => $this->faker
                ->regexify('[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4},[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}'),
        ];
    }
}
