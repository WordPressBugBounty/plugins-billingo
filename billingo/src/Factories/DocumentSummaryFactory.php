<?php

namespace App\Billingo\Factories;

use App\Billingo\Models\Document\DocumentVatRateSummary;

class DocumentSummaryFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'net_amount' => $this->faker->randomFloat(2, 0, 10000),
            'net_amount_local' => $this->faker->randomFloat(2, 0, 10000),
            'gross_amount_local' => $this->faker->randomFloat(2, 0, 10000),
            'vat_amount' => $this->faker->randomFloat(2, 0, 10000),
            'vat_amount_local' => $this->faker->randomFloat(2, 0, 10000),
            'vat_rate_summary' => [DocumentVatRateSummary::factory()],
        ];
    }
}
