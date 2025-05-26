<?php

namespace App\Billingo\Factories;

use App\Billingo\Enums\DocumentBlockTypeEnum;

class DocumentBlockFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'id' => $this->faker->numberBetween(1,1000),
            'name' =>$this->faker->regexify('[A-Za-z0-9 ]{10}'),
            'prefix' => $this->faker->word(),
            'custom_field1' => $this->faker->optional()->word(),
            'custom_field2' => $this->faker->optional()->word(),
            'type' => $this->faker->randomElement(getEnumValues(DocumentBlockTypeEnum::class)),
        ];
    }
}
