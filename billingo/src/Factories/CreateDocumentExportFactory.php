<?php

namespace App\Billingo\Factories;

use App\Billingo\Enums\DocumentExport\DocumentExportOtherOptionsEnum;
use App\Billingo\Enums\DocumentExport\DocumentExportQueryTypeEnum;
use App\Billingo\Enums\DocumentExport\DocumentExportSortByEnum;
use App\Billingo\Enums\DocumentExport\DocumentExportTypeEnum;
use App\Billingo\Enums\PaymentMethodEnum;
use App\Billingo\Models\DocumentExport\DocumentExportFilterExtra;

class CreateDocumentExportFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'query_type' => $this->faker->randomElement(getEnumValues(DocumentExportQueryTypeEnum::class)),
            'start_date' => $this->faker->date(),
            'end_date' => $this->faker->date(),
            'document_block_id' => $this->faker->optional()->numberBetween(1, 1000),
            'export_type' => $this->faker->randomElement(getEnumValues(DocumentExportTypeEnum::class)),
            'number_start_year' => $this->faker->optional()->numberBetween(1, 1000),
            'number_start_sequence' => $this->faker->optional()->numberBetween(1, 1000),
            'number_end_year' => $this->faker->optional()->numberBetween(1, 1000),
            'number_end_sequence' => $this->faker->optional()->numberBetween(1, 1000),
            'payment_method' => $this->faker->optional()->randomElement(getEnumValues(PaymentMethodEnum::class)),
            'sort_by' => $this->faker->optional()->randomElement(getEnumValues(DocumentExportSortByEnum::class)),
            'other_options' => $this->faker->optional()->randomElement(
                getEnumValues(DocumentExportOtherOptionsEnum::class)
            ),
            'filter_extra' => $this->faker->boolean() ? DocumentExportFilterExtra::factory() : null,
        ];
    }
}
