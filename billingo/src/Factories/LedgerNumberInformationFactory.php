<?php

namespace App\Billingo\Factories;

class LedgerNumberInformationFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'bevetel' => $this->faker->optional()->word(),
            'vevo' => $this->faker->optional()->word(),
            'penztar' => $this->faker->optional()->word(),
            'afa' => $this->faker->optional()->word(),
        ];
    }
}
