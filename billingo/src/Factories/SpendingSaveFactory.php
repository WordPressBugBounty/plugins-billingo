<?php

namespace App\Billingo\Factories;

use App\Billingo\Enums\CurrencyEnum;
use App\Billingo\Enums\Spending\CategoryEnum;
use App\Billingo\Enums\Spending\SpendingPaymentMethodEnum;

class SpendingSaveFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'currency' => $this->faker->randomElement(getEnumValues(CurrencyEnum::class)),
            'conversion_rate' => $this->faker->numberBetween(1, 10),
            'total_gross' => $this->faker->numberBetween(1, 1000),
            'total_gross_huf' => $this->faker->numberBetween(1, 1000),
            'total_vat_amount' => $this->faker->numberBetween(1, 1000),
            'total_vat_amount_huf' => $this->faker->numberBetween(1, 1000),
            'fulfillment_date' => $this->faker->date(),
            'paid_at' => $this->faker->optional()->date(),
            'category' => $this->faker->randomElement(getEnumValues(CategoryEnum::class)),
            'comment' => $this->faker->optional()->text(),
            'invoice_number' => $this->faker->regexify('(202[0-9]|203[0-9])-[09]{6}'),
            'invoice_date' => $this->faker->date(),
            'due_date' => $this->faker->date(),
            'payment_method' => $this->faker->randomElement(getEnumValues(SpendingPaymentMethodEnum::class)),
            'partner_id' => $this->faker->optional()->numberBetween(1, 1000),
        ];
    }
}
