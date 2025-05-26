<?php

namespace App\Billingo\Factories;

use App\Billingo\Enums\DiscountTypeEnum;

class DiscountFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(getEnumValues(DiscountTypeEnum::class)),
            'value' => $this->faker->numberBetween(1,100),
        ];
    }
}
