<?php

namespace App\Billingo\WooCommerce\Helpers;

use App\Billingo\WooCommerce\Traits\Admin_Init;

class WC_Billingo_Admin_Helper
{
    use Admin_Init;

    protected static bool $initialized = false;

    public static function init(): void
    {
        if (!self::$initialized && class_exists('WC_Order')) {
            self::$initialized = true;
            self::init_hooks();
        }
    }

    /**
     * Gets enabled payment gateways (WooCommerce)
     *
     * @return array
     */
    public static function get_available_payment_methods(): array
    {
        $payment_gateways = WC()->payment_gateways->payment_gateways();

        return array_map(fn($item) => $item->title,
            array_filter($payment_gateways, fn($gateway) => $gateway->enabled == 'yes'));
    }

}
