<?php

namespace App\Billingo\WooCommerce\Helpers;

use App\Billingo\WooCommerce\Repositories\Billingo_Repositroy;
use App\Billingo\WooCommerce\Traits\Standard_Init;

class WC_Billingo_Helper
{
    use Standard_Init;

    protected static $initialized = false;

    public static function init(): void
    {
        if (!self::$initialized && class_exists('WC_Order')) {

            self::$initialized = true;
            self::init_hooks();
        }
    }
}
