<?php

namespace App\Billingo\Factories;

class DocumentAncestorFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'id' => $this->faker->numberBetween(1, 1000),
            'invoice_number' => $this->faker->regexify('(202[0-9]|203[0-9])-[09]{6}'),
        ];
    }
}
