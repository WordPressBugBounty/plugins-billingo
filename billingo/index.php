<?php
/**
 * Plugin Name: Billingo & WooCommerce Official Plugin
 * Version: 3.6.3
 * Requires at least: 5.3
 * Requires PHP: 5.6
 * GitHub Plugin URI: https://github.com/octonull/webshop-plugins-woocommerce-public
 * GitHub Branch: main
 * License: GPL v2 or later
 * Description: Billingo online számlázó összeköttetés WooCommerce-hez
 * Author: Billingo Zrt. <hello@billingo.hu>
 * Author URI: https://billingo.hu
 * Text Domain: billingo
 */

if (!defined('WPINC')) {
    die();
}

define('BILLINGO__PLUGIN_URL', plugin_dir_url(__FILE__));
define('BILLINGO__PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once BILLINGO__PLUGIN_DIR . 'includes/PWSBillingo.php';
require_once BILLINGO__PLUGIN_DIR . 'includes/class-billingo.php';
register_activation_hook(__FILE__, ['WC_Billingo', 'install']);

add_action('init', ['WC_Billingo', 'init']);

if (is_admin()) {
    require_once BILLINGO__PLUGIN_DIR . 'admin/class-billingo-admin.php';
    add_action('init', ['Billingo_Admin', 'init']);
}