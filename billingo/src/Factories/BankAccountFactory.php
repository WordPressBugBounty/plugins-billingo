<?php

namespace App\Billingo\Factories;


use App\Billingo\Enums\CurrencyEnum;

class BankAccountFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'id' => $this->faker->optional()->numberBetween(1, 1000),
            'name' => $this->faker->name(),
            'account_number' => $this->faker->regexify('[A-Z]{2}--[0-9]{6}'),
            'account_number_iban' => $this->faker->optional()->regexify('[A-Z]{2}--[0-9]{6}'),
            'swift' => $this->faker->boolean
                ? strtoupper($this->faker->lexify('????').$this->faker->numerify('####'))
                : null,
            'currency' => $this->faker->randomElement(getEnumValues(CurrencyEnum::class)),
        ];
    }
}
