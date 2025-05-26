<?php

namespace App\Billingo\Factories;

use App\Billingo\Enums\ShippingModeEnum;
use App\Billingo\Models\Address;

class PartnerShippingFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'match' => $this->faker->boolean(),
            'name' => $this->faker->name(),
            'mode' => $this->faker->randomElement(getEnumValues(ShippingModeEnum::class)),
            'address' => Address::factory(),
        ];
    }
}
