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
    
    
        // Add admin notices and AJAX handlers for settings notification
        add_action('admin_notices', [self::class, 'show_settings_notification']);
        add_action('wp_ajax_billingo_dismiss_notification', [self::class, 'dismiss_settings_notification']);
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
        
        // Get the current invoice document
        $databaseRecord = $repository
            ->where('order_id', $order_id)
            ->where('type', TypeEnum::INVOICE->value)
            ->first();

        // Get all documents for this order
        $allDocuments = $repository
            ->where('order_id', $order_id)
            ->get();

        $typeValue = get_option('wc_billingo_manual_type');
        $enum = TypeEnum::tryFrom($typeValue);

        if ($enum) {
            $defaultDocumentType = $enum->getReadableText();
        } else {
            $defaultDocumentType = 'Számla'; // vagy valami alapértelmezett szöveg
        }
        
        $wcData = [
            'note' => get_option('wc_billingo_note'),
            'date' => wp_date('Y-m-d'),
        ];
        
        // Process the current document (for main display)
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

        $processedDocuments = [];
        if (!empty($allDocuments)) {
            foreach ($allDocuments as $doc) {
                $processedDocuments[] = [
                    'database_record' => $doc,
                    'document_data' => $doc,
                    'link' => $doc['link']
                ];
            
            }
            
            // Sort by creation date (newest first)
            usort($processedDocuments, function($a, $b) {
                $dateA = $a['database_record']['created_at'] ?? '';
                $dateB = $b['database_record']['created_at'] ?? '';
                return strcmp($dateB, $dateA); // Descending order
            });
        }

        $nonce = wp_create_nonce('wc_storno_invoice');

        echo view('Admin.billingo_metabox', [
            'isApiKeyMissing' => $isApiKeyMissing,
            'orderId' => $order_id,
            'nonce' => $nonce,
            'connectionError' => $connectionError ?? null,
            'document' => $document ?? null,
            'link' => $link ?? null,
            'defaultDocumentType' => $defaultDocumentType,
            'wcData' => $wcData,
            'allDocuments' => $processedDocuments,
        ]);
    }


    /**
     * Show settings notification popup after plugin activation or update
     */
    public static function show_settings_notification(): void
    {
        // Only show on admin pages and if the flag is set
        if (!get_option('wc_billingo_show_settings_notification', false)) {
            return;
        }

        // Only show to users who can manage options
        if (!current_user_can('manage_options')) {
            return;
        }

        // Only show on main admin pages (not on AJAX requests, etc.)
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        // Don't show on plugin/theme editor pages
        global $pagenow;
        if (in_array($pagenow, ['plugin-editor.php', 'theme-editor.php', 'customize.php'])) {
            return;
        }

        $settings_url = esc_url(add_query_arg([
            'page' => 'wc-settings',
            'tab' => 'settings_tab_billingo'
        ], admin_url('admin.php')));

        $nonce = wp_create_nonce('billingo_dismiss_notification');

        ?>
        <div id="billingo-settings-notification" class="notice notice-info is-dismissible" style="position: relative;">
            <div style="display: flex; align-items: center; padding: 10px 0;">
                <div style="margin-right: 15px;">
                    <span class="dashicons dashicons-admin-plugins" style="font-size: 32px; color: #00a0d2; width: 32px; height: 32px;"></span>
                </div>
                <div style="flex: 1;">
                    <h3 style="margin: 0 0 5px 0;"><?php _e('Billingo plugin telepítve/frissítve!', 'billingo'); ?></h3>
                    <p style="margin: 0;">
                        <?php echo esc_html__('Kérjük, ellenőrizze a Billingo beállításokat a megfelelő működés érdekében.', 'billingo'); ?>
                    </p>
                </div>
                <div style="margin-left: 15px;">
                    <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary">
                        <?php echo esc_html__('Beállítások ellenőrzése', 'billingo'); ?>
                    </a>
                    <button type="button" class="button" onclick="billingoDismissNotification('<?php echo ($nonce); ?>')">
                        <?php echo esc_html__('Később', 'billingo'); ?>
                    </button>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        function billingoDismissNotification(nonce) {
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'billingo_dismiss_notification',
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        jQuery('#billingo-settings-notification').fadeOut();
                    }
                },
                error: function() {
                    // Fallback: just hide the notification if AJAX fails
                    jQuery('#billingo-settings-notification').fadeOut();
                }
            });
        }

        // Handle WordPress default dismiss button
        jQuery(document).on('click', '#billingo-settings-notification .notice-dismiss', function() {
            billingoDismissNotification('<?php echo $nonce; ?>');
        });
        </script>

        <style>
        #billingo-settings-notification {
            border-left-color: #00a0d2 !important;
        }
        #billingo-settings-notification h3 {
            color: #23282d;
        }
        #billingo-settings-notification .dashicons {
            line-height: 32px;
        }
        </style>
        <?php
    }

    /**
     * AJAX handler to dismiss the settings notification
     */
    public static function dismiss_settings_notification(): void
    {
        // Check if this is a valid AJAX request
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            wp_die('Invalid request');
        }

        // Verify nonce
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'billingo_dismiss_notification')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        // Remove the notification flag
        $result = delete_option('wc_billingo_show_settings_notification');

        if ($result || !get_option('wc_billingo_show_settings_notification', false)) {
            wp_send_json_success(['message' => 'Notification dismissed successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to dismiss notification']);
        }
    }

}
