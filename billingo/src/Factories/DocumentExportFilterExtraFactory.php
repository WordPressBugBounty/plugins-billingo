<?php

namespace App\Billingo\Factories;

use App\Billingo\Models\DocumentExport\LedgerNumberInformation;

class DocumentExportFilterExtraFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'tensoft_vkod' => $this->faker->optional()->regexify('[A-Z]{3}--[0-9]{4}'),
            'ledger_number' => $this->faker->boolean() ? LedgerNumberInformation::factory() : null,
            'forintsoft_konyvelesi_naplo_szam' => $this->faker->optional()->regexify('[A-Z]{2}-[0-9]{5}'),
            'positive_ledger_number' => $this->faker->optional()->regexify('[A-Z]{1}-[0-9]{5}'),
            'negative_ledger_number' => $this->faker->optional()->regexify('[A-Z]{1}-[0-9]{5}'),
            'rlb_kata' => $this->faker->optional()->boolean(),
            'rlb_note' => $this->faker->optional()->word(),
            'novitax_naplokod' => $this->faker->optional()->regexify('[A-Z]{5}--[0-9]{2}'),
            'use_gross_values' => $this->faker->optional()->boolean(),
        ];
    }
}
