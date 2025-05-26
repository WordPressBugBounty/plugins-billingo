<?php

namespace App\Billingo\Factories;

use App\Billingo\Enums\CurrencyEnum;
use App\Billingo\Enums\Document\FormatEnum;
use App\Billingo\Enums\Document\LanguageEnum;
use App\Billingo\Enums\PaymentMethodEnum;
use App\Billingo\Models\Discount;

class CustomBillingoSettingsFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'payment_method' => $this->faker->randomElement(getEnumValues(PaymentMethodEnum::class)),
            'document_form' => $this->faker->randomElement(getEnumValues(FormatEnum::class)),
            'due_days' => $this->faker->numberBetween(1, 365),
            'document_currency' => $this->faker->randomElement(getEnumValues(CurrencyEnum::class)),
            'template_language_code' => $this->faker->randomElement(getEnumValues(LanguageEnum::class)),
            'discount' => Discount::factory(),
        ];
    }
}
