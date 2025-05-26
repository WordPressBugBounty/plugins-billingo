<?php

namespace App\Billingo\Factories;

use App\Billingo\Models\Address;
use App\Billingo\Models\Document\DocumentBankAccount;

class OrganizationFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'tax_number' => $this->faker->regexify('[0-9]{8}-[0-9]{1}-[0-9]{2}'),
            'bank_account' => DocumentBankAccount::factory(),
            'address' => Address::factory(),
            'small_taxpayer' => $this->faker->boolean(),
            'ev_number' => $this->faker->regexify('[0-9]{8}'),
            'eu_tax_number' => $this->faker->regexify('EU[0-9]{10}'),
            'cash_settled' => $this->faker->boolean(),
        ];
    }
}
