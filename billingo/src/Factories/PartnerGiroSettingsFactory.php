<?php

namespace App\Billingo\Factories;

class PartnerGiroSettingsFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'giro_default_settings' => $this->faker->boolean(),
            'giro_payment_request_enabled' => $this->faker->boolean(),
            'giro_different_amount_allowed' => $this->faker->boolean(),
        ];
    }
}
