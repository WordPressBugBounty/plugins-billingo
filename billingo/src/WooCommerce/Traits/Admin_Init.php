<?php

namespace App\Billingo\WooCommerce\Traits;

use App\Billingo\Enums\Document\TypeEnum;
use App\Billingo\Service\BillingoClient;
use App\Billingo\WooCommerce\Controllers\WC_Billingo_Admin_Controller;
use App\Billingo\WooCommerce\Repositories\Billingo_Repositroy;
use App\Billingo\WooCommerce\Service\Billingo_Logger;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Symfony\Component\HttpFoundation\Response;

trait Admin_Init
{
    public static function init_hooks(): void
    {
        add_action('admin_init', [self::class, 'wc_billingo_admin_init']);
        add_filter('woocommerce_settings_tabs_array', [self::class, 'add_settings_tab'], 50);
        add_action('woocommerce_settings_tabs_settings_tab_billingo', [self::class, 'settings_tab']);
        add_action('woocommerce_update_options_settings_tab_billingo', [self::class, 'update_settings']);
        add_action('add_meta_boxes', [self::class, 'wc_billingo_add_metabox']);
        add_filter('plugin_action_links_billingonew/index.php', [self::class, 'add_wp_settings_link']);
    }

    /**
     * Enqueue admin assets
     */
    public static function wc_billingo_admin_init(): void
    {
        $plugin_data = get_plugin_data(BILLINGO__PLUGIN_DIR . 'index.php');
        $plugin_version = $plugin_data['Version'];

        wp_enqueue_script(
            'billingo_js',
            plugins_url('/../admin/js/global.js', __FILE__),
            ['jquery'],
            $plugin_version,
            true);

        $wc_billingo_local = ['loading' => plugins_url('/../admin/images/ajax-loader.gif', __FILE__)];
        wp_localize_script('billingo_js', 'wc_billingo_params', $wc_billingo_local);
    }

    /**
     * Adds Billingo tab to WooCommerce Settings page
     *
     * @param array $settings_tabs WooCommerce/Settings Tabs
     *
     * @return array $settings_tabs
     */
    public static function add_settings_tab(array $settings_tabs): array
    {
        $settings_tabs['settings_tab_billingo'] = __('Billingo', 'billingo');
        return $settings_tabs;
    }

    /**
     * Outputs admin fields
     */
    public static function settings_tab(): void
    {
        $controller = new WC_Billingo_Admin_Controller();
        $controller->render_settings_page();
    }

    /**
     * Updates all settings which are passed
     */
    public static function update_settings(): void
    {
        // controller handles the settings save
        $controller = new WC_Billingo_Admin_Controller();
        
        // CSRF check
        if (
            !isset($_POST['_wpnonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'woocommerce-settings')
        )  {
            $controller->CSRF_check_failiure_alert();
            return;
        }

        // give the current subsection to the controller
        $current_subsection = isset($_GET['subsection']) ?
            sanitize_text_field(wp_unslash($_GET['subsection'])) :
            'api';

        $controller->process_settings_save($current_subsection);
    }

    public static function add_wp_settings_link(array $links): array
    {
        $url = esc_url(add_query_arg([
            'page' => 'wc-settings',
            'tab' => 'settings_tab_billingo'
        ], get_admin_url() . 'admin.php'));

        array_push($links, '<a href="' . $url . '">' . __('Settings', 'billingo') . '</a>');

        return $links;
    }

    public static function wc_billingo_add_metabox()
    {
        $screen = wc_get_container()
            ->get(CustomOrdersTableController::class)
            ->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'custom_order_option',
            'Billingo számla',
            [self::class, 'render_meta_box_content'],
            $screen,
            'side');
    }

    public static function render_meta_box_content($order)
    {
        if (is_object($order)) {
            if (method_exists($order, 'get_id')) {
                $order_id = $order->get_id();
            } elseif (property_exists($order, 'ID')) {
                $order_id = $order->ID;
            } elseif (method_exists($order, 'ID')) {
                $order_id = $order->ID();
            } else {
                $order_id = null; // vagy dobj hibát
            }
        }
        $repository = new Billingo_Repositroy();
        $client = new BillingoClient(get_option('wc_billingo_api_key'));

        $isApiKeyMissing = !get_option('wc_billingo_api_key');
        $databaseRecord = $repository
            ->where('order_id', $order_id)
            ->where('type', TypeEnum::INVOICE->value)
            ->first();

        $typeValue = get_option('wc_order_manual_type');
        $enum = TypeEnum::tryFrom($typeValue);

        if ($enum) {
            $defaultDocumentType = $enum->getReadableText();
        } else {
            $defaultDocumentType = 'Ismeretlen típus'; // vagy valami alapértelmezett szöveg
        }
        
        $wcData = [
            'note' => get_option('wc_billingo_note'),
            'date' => wp_date('Y-m-d'),
        ];
        if ($databaseRecord) {
            $link = $databaseRecord['link'];
            $response = $client->document()->getById($databaseRecord['billingo_id'])->getResponse();
            $connectionError = $response->getStatusCode() === Response::HTTP_OK;
            $document = $connectionError
                ? $response->getData()->toArray()
                : null;

            if (is_null($document)) {
                Billingo_Logger::warning('Connection error: ' . json_encode($response->getErrors()));
            }
        }

        $nonce = wp_create_nonce('wc_storno_invoice');

        echo view('Admin.billingo_metabox', [
            'isApiKeyMissing' => $isApiKeyMissing,
            'orderId' => $order_oid,
            'nonce' => $nonce,
            'connectionError' => $connectionError ?? null,
            'document' => $document ?? null,
            'link' => $link ?? null,
            'defaultDocumentType' => $defaultDocumentType,
            'wcData' => $wcData,
        ]);
    }
}
