<?php

namespace App\Billingo\Enums\WooCommerce;

enum OrderStatusEnum: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case ON_HOLD = 'on-hold';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case FAILED = 'failed';
    case CHECKOUT_DRAFT = 'checkout-draft';
}
