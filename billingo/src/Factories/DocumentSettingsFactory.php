<?php

namespace App\Billingo\Factories;

use App\Billingo\Enums\Document\TypeEnum;
use App\Billingo\Enums\DontSendToNavReasonEnum;
use App\Billingo\Enums\OnlinePaymentEnum;
use App\Billingo\Enums\RoundEnum;
use App\Billingo\Models\Document\DocumentInstantPaymentRequest;

class DocumentSettingsFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'mediated_service' => $this->faker->boolean(),
            'without_financial_fulfillment' => $this->faker->boolean(),
            'online_payment' => $this->faker->randomElement(getEnumValues(OnlinePaymentEnum::class)),
            'should_send_email' => $this->faker->boolean(),
            'round' => $this->faker->randomElement(getEnumValues(RoundEnum::class)),
            'dont_send_to_nav_reason' => $this->faker->randomElement(getEnumValues(DontSendToNavReasonEnum::class)),
            'order_number' => $this->faker->bothify('ORD###'),
            'place_id' => $this->faker->numberBetween(1, 1000),
            'instant_payment' => $this->faker->boolean(),
            'selected_type' => $this->faker->randomElement(getEnumValues(TypeEnum::class)),
            'instant_payment_request' => DocumentInstantPaymentRequest::factory(),
        ];
    }
}
