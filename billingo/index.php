<?php
/**
 * Plugin Name: Billingo Official for WooCommerce
 * Version: 4.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * License: GPL v2 or later
 * Description: Billingo online számlázó összeköttetés WooCommerce-hez
 * Author: Billingo Zrt. <hello@billingo.hu>
 * Author URI: https://billingo.hu
 * Text Domain: billingo

 */

//stop install if php version is less than 8.1
if (version_compare(PHP_VERSION, '8.1', '<')) {
    wp_die('A Billingo új 4.0-as verziójú modul használatához legalább PHP 8.1 verzió szükséges.');
}

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define('BILLINGO__PLUGIN_URL', plugin_dir_url(__FILE__));
define('BILLINGO__PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once BILLINGO__PLUGIN_DIR . 'vendor/autoload.php';

use App\Billingo\WooCommerce\Helpers\WC_Billingo_Admin_Helper;
use App\Billingo\WooCommerce\Helpers\WC_Billingo_Helper;
use App\Billingo\WooCommerce\Repositories\Billingo_Repositroy;

register_activation_hook(__FILE__, [Billingo_Repositroy::class, 'install']);

add_action('init', [WC_Billingo_Helper::class, 'init']);

if (is_admin()) {
    add_action('init', [WC_Billingo_Admin_Helper::class, 'init']);
}
