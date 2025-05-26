<?php

namespace App\Billingo\Factories;

class DocumentInstantPaymentRequestFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'debtor_bank_account' =>$this->faker->regexify('[A-Z]{2}--[0-9]{6}'),
            'different_amount_allowed' => $this->faker->boolean(),
            'update_partner_payment_details' => $this->faker->boolean(),
        ];
    }
}
