<?php

namespace App\Billingo\Factories;

class DocumentCancellationFactory extends BaseFactory
{
    public function definition(): array
    {
        $cancellationReasons = [
            'Customer requested cancellation',
            'Product out of stock',
            'Shipping address issues',
            'Payment problems',
            'Order placed by mistake',
            'Better price found elsewhere',
            'Changed mind',
            'Item not needed anymore',
            'Delivery time too long',
            'Incorrect order'
        ];

        $cancellationRecipients = [
            'customer_service@example.com',
            'support_team@example.com',
            'returns_department@example.com',
            'billing@example.com',
            'manager@example.com'
        ];

        return [
            'cancellation_reason' => $this->faker->randomElement($cancellationReasons),
            'cancellation_recipients' => $this->faker->randomElement($cancellationRecipients),
        ];
    }
}
