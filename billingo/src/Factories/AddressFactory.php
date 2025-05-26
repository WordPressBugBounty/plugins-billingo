<?php

namespace App\Billingo\Factories;

use App\Billingo\Enums\CountryEnum;

class AddressFactory extends BaseFactory
{
    public function definition(): array
    {
        return [
            'country_code' => $this->faker->randomElement(getEnumValues(CountryEnum::class)),
            'post_code' => $this->faker->regexify('[1-9]{1}[0-9]{3}'),
            'city' => $this->faker->city,
            'address' => $this->faker->address,
        ];
    }
}