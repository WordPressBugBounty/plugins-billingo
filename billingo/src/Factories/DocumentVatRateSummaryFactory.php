<?php

namespace App\Billingo\Factories;

class DocumentVatRateSummaryFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'vat_name' => $this->faker->word(),
            'vat_percentage' => $this->faker->randomFloat(2, 0, 100),
            'vat_rate_net_amount' => $this->faker->randomFloat(2, 0, 10000),
            'vat_rate_vat_amount' => $this->faker->randomFloat(2, 0, 10000),
            'vat_rate_vat_amount_local' => $this->faker->randomFloat(2, 0, 10000),
            'vat_rate_gross_amount' => $this->faker->randomFloat(2, 0, 10000),
        ];
    }
}
