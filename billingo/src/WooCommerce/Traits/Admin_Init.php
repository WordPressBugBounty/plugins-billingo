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

        // Add hooks for orders admin page document icons
        add_action('manage_woocommerce_page_wc-orders_custom_column', [self::class, 'display_order_documents_in_status_column'], 10, 2);
        add_action('manage_shop_order_posts_custom_column', [self::class, 'display_order_documents_in_status_column'], 10, 2);
        add_action('admin_head', [self::class, 'add_order_documents_styles']);
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
        if ( ! current_user_can('manage_woocommerce') ) {
            wp_die( esc_html__( 'You do not have permission to manage Billingo settings.', 'billingo' ) );
        }

        $controller = new WC_Billingo_Admin_Controller();
        $controller->render_settings_page();
    }

    /**
     * Updates all settings which are passed
     */
    public static function update_settings(): void
    {
        // 1) Jogosultság: Admin + Shop Manager (WooCommerce capability)
        if ( ! current_user_can('manage_woocommerce') ) {
            return;
        }

        $controller = new WC_Billingo_Admin_Controller();

        // 2) CSRF check – ezt jól csinálod, marad
        if (
                !isset($_POST['_wpnonce']) ||
                !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'woocommerce-settings')
        )  {
            $controller->CSRF_check_failiure_alert();
            return;
        }

        $current_subsection = isset($_GET['subsection'])
                ? sanitize_text_field(wp_unslash($_GET['subsection']))
                : 'api';

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
        $repository->withCanceled();
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
                    <h3 style="margin: 0 0 5px 0;"><?php esc_html_e( 'Billingo plugin telepítve/frissítve!', 'billingo' ); ?></h3>
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

    public static function display_order_documents_in_status_column($column, $order_or_order_id)
    {
        if ($column === 'order_status') {
            // Handle both HPOS and legacy orders
            if ($order_or_order_id instanceof \WC_Order) {
                // HPOS - $order_or_order_id is already an order object
                $order = $order_or_order_id;
                $order_id = $order->get_id();
            } else {
                // Legacy or it's an order ID
                $order_id = $order_or_order_id;
                $order = wc_get_order($order_id);
            }

            if (!$order) {
                return;
            }

            $repository = new Billingo_Repositroy();
            $repository->withCanceled();

            // Get all documents for this order
            $allDocuments = $repository
                    ->where('order_id', $order_id)
                    ->get();

            if (!empty($allDocuments)) {
                // Sort by creation date (newest first)
                usort($allDocuments, function($a, $b) {
                    $dateA = $a['created_at'] ?? '';
                    $dateB = $b['created_at'] ?? '';
                    return strcmp($dateB, $dateA); // Descending order (newest first)
                });

                // Build the document icons HTML with responsive behavior
                $documentsHtml = '<div class="billingo-document-icons" style="display: inline-block; margin-left: 5px;">';

                // For screens 1300px+: show max 2 documents
                // For screens 800px-1299px: show max 1 document  
                // For screens <800px: hide completely

                // Show maximum 2 documents for large screens, 1 for medium screens
                $documentsHtml .= '<div class="billingo-docs-large-screen" style="display: inline-block;">';
                $displayedDocsLarge = array_slice($allDocuments, 0, 2);
                $remainingDocsLarge = array_slice($allDocuments, 2);

                foreach ($displayedDocsLarge as $doc) {
                    $documentsHtml .= self::generateDocumentIcon($doc);
                }

                // Add "+" indicator if there are more documents (large screens)
                if (!empty($remainingDocsLarge)) {
                    $documentsHtml .= self::generatePlusIcon($remainingDocsLarge);
                }
                $documentsHtml .= '</div>';

                // Show maximum 1 document for medium screens (800px-1299px)
                $documentsHtml .= '<div class="billingo-docs-medium-screen" style="display: none;">';
                $displayedDocsMedium = array_slice($allDocuments, 0, 1);
                $remainingDocsMedium = array_slice($allDocuments, 1);

                foreach ($displayedDocsMedium as $doc) {
                    $documentsHtml .= self::generateDocumentIcon($doc);
                }

                // Add "+" indicator if there are more documents (medium screens)
                if (!empty($remainingDocsMedium)) {
                    $documentsHtml .= self::generatePlusIcon($remainingDocsMedium);
                }
                $documentsHtml .= '</div>';

                $documentsHtml .= '</div>';

                // Simply echo the HTML - it will be appended to the status column content
                echo $documentsHtml;
            }
        }
    }

    private static function generateDocumentIcon($doc)
    {
        $docType = $doc['type'];
        $title = '';
        $svg = '';
        //todo: refactor: move svg-s to a partial "view" files, in email_settings.twig also, if wp did'nt mention the svg loading need to escape
        switch ($docType) {
            case 'proforma':
                $title = TypeEnum::PROFORMA->getReadableText();
                $svg = '<svg width="32" height="32" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <g clip-path="url(#clip0_33_1278)">
                    <path d="M38 0H6C2.68629 0 0 2.68629 0 6V38C0 41.3137 2.68629 44 6 44H38C41.3137 44 44 41.3137 44 38V6C44 2.68629 41.3137 0 38 0Z" fill="#FCE3B6"/>
                    <path d="M26.0249 24.25H18.0249C17.826 24.25 17.6352 24.329 17.4946 24.4697C17.3539 24.6103 17.2749 24.8011 17.2749 25C17.2749 25.1989 17.3539 25.3897 17.4946 25.5303C17.6352 25.671 17.826 25.75 18.0249 25.75H26.0249C26.2238 25.75 26.4146 25.671 26.5552 25.5303C26.6959 25.3897 26.7749 25.1989 26.7749 25C26.7749 24.8011 26.6959 24.6103 26.5552 24.4697C26.4146 24.329 26.2238 24.25 26.0249 24.25Z" fill="#C68F2E"/>
                    <path d="M26.0249 20.25H18.0249C17.826 20.25 17.6352 20.329 17.4946 20.4697C17.3539 20.6103 17.2749 20.8011 17.2749 21C17.2749 21.1989 17.3539 21.3897 17.4946 21.5303C17.6352 21.671 17.826 21.75 18.0249 21.75H26.0249C26.2238 21.75 26.4146 21.671 26.5552 21.5303C26.6959 21.3897 26.7749 21.1989 26.7749 21C26.7749 20.8011 26.6959 20.6103 26.5552 20.4697C26.4146 20.329 26.2238 20.25 26.0249 20.25Z" fill="#C68F2E"/>
                    <path d="M29.8051 10.958H18.0251C17.894 10.9577 17.7641 10.9834 17.6429 11.0335C17.5218 11.0836 17.4117 11.1572 17.3191 11.25L11.2681 17.292C11.1751 17.385 11.1014 17.4953 11.0511 17.6168C11.0008 17.7383 10.975 17.8685 10.9751 18V27.785C10.9756 28.3721 11.209 28.9351 11.6241 29.3503C12.0391 29.7656 12.602 29.9992 13.1891 30H26.8351L31.5001 32.892C31.6581 32.9899 31.8402 33.0419 32.0261 33.042C32.1964 33.0419 32.3638 32.9989 32.5131 32.917C32.6689 32.8302 32.7986 32.7032 32.8888 32.5494C32.979 32.3955 33.0264 32.2204 33.0261 32.042V14.178C33.0253 13.3241 32.6857 12.5054 32.0818 11.9017C31.4778 11.2979 30.659 10.9585 29.8051 10.958ZM17.0251 14.369V17H14.3911L17.0251 14.369ZM31.0251 30.246L27.6461 28.146C27.4877 28.0495 27.3056 27.999 27.1201 28H13.1891C13.1609 28 13.133 27.9944 13.107 27.9836C13.0809 27.9728 13.0573 27.957 13.0374 27.937C13.0175 27.917 13.0018 27.8933 12.9911 27.8672C12.9804 27.8411 12.975 27.8132 12.9751 27.785V19H18.0251C18.2903 19 18.5447 18.8947 18.7322 18.7071C18.9197 18.5196 19.0251 18.2652 19.0251 18V12.958H29.8051C30.1286 12.9583 30.4387 13.0869 30.6675 13.3156C30.8962 13.5444 31.0248 13.8545 31.0251 14.178V30.246Z" fill="#C68F2E"/>
                    </g>
                    <defs>
                    <clipPath id="clip0_33_1278">
                    <rect width="44" height="44" fill="white"/>
                    </clipPath>
                    </defs>
                    </svg>';
                break;
            case 'invoice':
                $title = TypeEnum::INVOICE->getReadableText();
                $svg = '<svg width="32" height="32" viewBox="0 0 45 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <g clip-path="url(#clip0_33_1327)">
                    <path d="M38.6665 0H6.6665C3.3528 0 0.666504 2.68629 0.666504 6V38C0.666504 41.3137 3.3528 44 6.6665 44H38.6665C41.9802 44 44.6665 41.3137 44.6665 38V6C44.6665 2.68629 41.9802 0 38.6665 0Z" fill="#B5E7F2"/>
                    <path d="M18.6665 20.25C18.4676 20.25 18.2768 20.329 18.1362 20.4697C17.9955 20.6103 17.9165 20.8011 17.9165 21C17.9165 21.1989 17.9955 21.3897 18.1362 21.5303C18.2768 21.671 18.4676 21.75 18.6665 21.75H26.6665C26.8654 21.75 27.0562 21.671 27.1968 21.5303C27.3375 21.3897 27.4165 21.1989 27.4165 21C27.4165 20.8011 27.3375 20.6103 27.1968 20.4697C27.0562 20.329 26.8654 20.25 18.6665 20.25Z" fill="#00668B"/>
                    <path d="M26.6665 25.25H18.6665C18.4676 25.25 18.2768 25.329 18.1362 25.4697C17.9955 25.6103 17.9165 25.8011 17.9165 26C17.9165 26.1989 17.9955 26.3897 18.1362 26.5303C18.2768 26.671 18.4676 26.75 18.6665 26.75H26.6665C26.8654 26.75 27.0562 26.671 27.1968 26.5303C27.3375 26.3897 27.4165 26.1989 27.4165 26C27.4165 25.8011 27.3375 25.6103 27.1968 25.4697C27.0562 25.329 26.8654 25.25 26.6665 25.25Z" fill="#00668B"/>
                    <path d="M30.6665 11H20.6665C20.5339 11 20.4027 11.0265 20.2805 11.078C20.1759 11.124 20.0801 11.188 19.9975 11.267C19.9865 11.278 19.9705 11.282 19.9585 11.293L13.9585 17.293C13.7722 17.4812 13.6673 17.7351 13.6665 18V32C13.6665 32.2652 13.7719 32.5196 13.9594 32.7071C14.1469 32.8946 14.4013 33 14.6665 33H30.6665C30.9317 33 31.1861 32.8946 31.3736 32.7071C31.5611 32.5196 31.6665 32.2652 31.6665 32V12C31.6665 11.7348 31.5611 11.4804 31.3736 11.2929C31.1861 11.1054 30.9317 11 30.6665 11ZM19.6665 14.414V17H17.0805L19.6665 14.414ZM29.6665 31H15.6665V19H20.6665C20.9317 19 21.1861 18.8946 21.3736 18.7071C21.5611 18.5196 21.6665 18.2652 21.6665 18V13H29.6665V31Z" fill="#00668B"/>
                    </g>
                    <defs>
                    <clipPath id="clip0_33_1327">
                    <rect width="44" height="44" fill="white" transform="translate(0.666504)"/>
                    </clipPath>
                    </defs>
                    </svg>';
                break;
            case 'cancellation':
                $title = TypeEnum::CANCELLATION->getReadableText();
                $svg = '<svg width="32" height="32" viewBox="0 0 45 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <g clip-path="url(#clip0_33_1336)">
                    <path d="M38.3335 0.00201416H6.3335C3.01979 0.00201416 0.333496 2.68831 0.333496 6.00201V38.002C0.333496 41.3157 3.01979 44.002 6.3335 44.002H38.3335C41.6472 44.002 44.3335 41.3157 44.3335 38.002V6.00201C44.3335 2.68831 41.6472 0.00201416 38.3335 0.00201416Z" fill="#D6E3EA"/>
                    <path d="M22.8336 23.439L20.8636 21.47C20.7215 21.3375 20.5334 21.2654 20.3391 21.2689C20.1448 21.2723 19.9594 21.351 19.822 21.4884C19.6846 21.6258 19.6059 21.8112 19.6025 22.0055C19.599 22.1998 19.6712 22.3879 19.8036 22.53L21.7726 24.5L19.8036 26.47C19.73 26.5387 19.6709 26.6215 19.6299 26.7135C19.5889 26.8055 19.5668 26.9048 19.5651 27.0055C19.5633 27.1062 19.5818 27.2062 19.6195 27.2996C19.6572 27.393 19.7134 27.4778 19.7846 27.5491C19.8558 27.6203 19.9407 27.6764 20.0341 27.7142C20.1274 27.7519 20.2275 27.7704 20.3282 27.7686C20.4289 27.7668 20.5282 27.7448 20.6202 27.7038C20.7122 27.6628 20.795 27.6037 20.8636 27.53L22.8336 25.561L24.8036 27.53C24.9458 27.6625 25.1339 27.7346 25.3282 27.7312C25.5225 27.7278 25.7079 27.6491 25.8453 27.5117C25.9827 27.3742 26.0614 27.1889 26.0648 26.9946C26.0683 26.8003 25.9961 26.6122 25.8636 26.47L23.8946 24.5L25.8636 22.53C25.9961 22.3879 26.0683 22.1998 26.0648 22.0055C26.0614 21.8112 25.9827 21.6258 25.8453 21.4884C25.7079 21.351 25.5225 21.2723 25.3282 21.2689C25.1339 21.2654 24.9458 21.3375 24.8036 21.47L22.8336 23.439Z" fill="#5A748E"/>
                    <path d="M30.3335 11H20.3335C20.2009 11 20.0697 11.0265 19.9475 11.078C19.8429 11.124 19.7471 11.188 19.6645 11.267C19.6535 11.278 19.6375 11.282 19.6255 11.293L13.6255 17.293C13.4391 17.4812 13.3343 17.7351 13.3335 18V32C13.3335 32.2652 13.4389 32.5196 13.6264 32.7071C13.8139 32.8946 14.0683 33 14.3335 33H30.3335C30.5987 33 30.8531 32.8946 31.0406 32.7071C31.2281 32.5196 31.3335 32.2652 31.3335 32V12C31.3335 11.7348 31.2281 11.4804 31.0406 11.2929C30.8531 11.1054 30.5987 11 30.3335 11ZM19.3335 14.414V17H16.7475L19.3335 14.414ZM29.3335 31H15.3335V19H20.3335C20.5987 19 20.8531 18.8946 21.0406 18.7071C21.2281 18.5196 21.3335 18.2652 21.3335 18V13H29.3335V31Z" fill="#5A748E"/>
                    </g>
                    <defs>
                    <clipPath id="clip0_33_1336">
                    <rect width="44" height="44" fill="white" transform="translate(0.333496)"/>
                    </clipPath>
                    </defs>
                    </svg>';
                break;
            default:
                return ''; // Skip unknown document types
        }

        return '<span class="billingo-document-icon" title="' . esc_attr($title . ': ' . $doc['billingo_number']) . '" style="margin-left: 3px; vertical-align: middle;">' . $svg . '</span>';
    }

    private static function generatePlusIcon($remainingDocs)
    {
        $tooltipText = '';
        foreach ($remainingDocs as $doc) {
            $docType = $doc['type'];
            $title = '';

            switch ($docType) {
                case 'proforma':
                    $title = 'Díjbekérő';
                    break;
                case 'invoice':
                    $title = 'Számla';
                    break;
                case 'cancellation':
                    $title = 'Sztornó';
                    break;
                default:
                    $title = ucfirst($docType);
            }

            $tooltipText .= $title . ': ' . $doc['billingo_number'] . "\n";
        }

        return '<span class="billingo-more-docs" title="' . esc_attr(trim($tooltipText)) . '" style="margin-left: 3px; vertical-align: middle; background: #ddd; color: #333; border-radius: 50%; width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; cursor: help;">+</span>';
    }

    public static function add_order_documents_styles()
    {
        // Only add styles on orders admin page
        $screen = get_current_screen();
        if ($screen && ($screen->id === 'woocommerce_page_wc-orders' || $screen->post_type === 'shop_order')) {
            echo '<style>
            /* Large screens (1300px+): Show 2 documents max */
            @media (min-width: 1300px) {
                .billingo-document-icons {
                    display: inline-block !important;
                    margin-left: 5px;
                    vertical-align: middle;
                }
                .billingo-docs-large-screen {
                    display: inline-block !important;
                }
                .billingo-docs-medium-screen {
                    display: none !important;
                }
                .billingo-document-icon {
                    margin-left: 3px;
                    margin-right: 2px;
                    vertical-align: middle;
                    cursor: help;
                }
                .billingo-document-icon svg {
                    width: 32px;
                    height: 32px;
                    vertical-align: middle;
                }
                .billingo-more-docs {
                    margin-left: 3px;
                    vertical-align: middle;
                    background: #ddd !important;
                    color: #333 !important;
                    border-radius: 50% !important;
                    width: 24px !important;
                    height: 24px !important;
                    display: inline-flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    font-size: 14px !important;
                    font-weight: bold !important;
                    cursor: help !important;
                    transition: all 0.2s ease !important;
                    border: none !important;
                    text-decoration: none !important;
                }
                .billingo-more-docs:hover {
                    background: #bbb !important;
                    transform: scale(1.1) !important;
                }
                /* Ensure proper alignment with status mark */
                .order-status {
                    display: inline-block;
                    vertical-align: middle;
                }
            }
            
            /* Medium screens (800px-1299px): Show 1 document max */
            @media (min-width: 370px) and (max-width: 1299px) {
                .billingo-document-icons {
                    display: inline-block !important;
                    margin-left: 5px;
                    vertical-align: middle;
                }
                .billingo-docs-large-screen {
                    display: none !important;
                }
                .billingo-docs-medium-screen {
                    display: inline-block !important;
                }
                .billingo-document-icon {
                    margin-left: 3px;
                    margin-right: 2px;
                    vertical-align: middle;
                    cursor: help;
                }
                .billingo-document-icon svg {
                    width: 32px;
                    height: 32px;
                    vertical-align: middle;
                }
                .billingo-more-docs {
                    margin-left: 3px;
                    vertical-align: middle;
                    background: #ddd !important;
                    color: #333 !important;
                    border-radius: 50% !important;
                    width: 24px !important;
                    height: 24px !important;
                    display: inline-flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    font-size: 14px !important;
                    font-weight: bold !important;
                    cursor: help !important;
                    transition: all 0.2s ease !important;
                    border: none !important;
                    text-decoration: none !important;
                }
                .billingo-more-docs:hover {
                    background: #bbb !important;
                    transform: scale(1.1) !important;
                }
                /* Ensure proper alignment with status mark */
                .order-status {
                    display: inline-block;
                    vertical-align: middle;
                }
            }
            
            @media (min-width: 782px) and (max-width: 1111px) {
                .billingo-document-icons {
                    margin-top: 5px !important;
                }
            }
            
            /* Below 782px: Different DOM structure - icons before mark tag */
            @media (min-width: 370px) and (max-width: 781px) {
                .billingo-document-icons {
                    margin-right: 5px !important;
                    margin-left: 0 !important;
                    display: inline-block !important;
                    vertical-align: middle;
                }
                .order_status.small-screen-only .order-status {
                    display: inline-block;
                    vertical-align: middle;
                }
            }
                /* under 630 pixel add bottom margin to the document icons */
                @media (max-width: 630px) {
                    .billingo-document-icons {
                        margin-bottom: 5px !important;
                    }
                }
            
            /* Small screens (below 370px): Hide completely */
            @media (max-width: 369px) {
                .billingo-document-icons {
                    display: none !important;
                }
            }
            </style>';
        }
    }
}
