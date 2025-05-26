<?php

namespace App\Billingo\Factories;

class DocumentBankAccountFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'id' => $this->faker->numberBetween(1,1000),
            'name' => $this->faker->name(),
            'account_number' => $this->faker->regexify('[A-Z]{2}--[0-9]{6}'),
            'account_number_iban' => $this->faker->boolean(30)
                ? null
                : $this->faker->regexify('[A-Z]{2}--[0-9]{6}'),
            'swift' => strtoupper($this->faker->lexify('????') . $this->faker->numerify('####'))
        ];
    }
}
