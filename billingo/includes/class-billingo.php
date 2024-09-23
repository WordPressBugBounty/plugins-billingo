<?php

if (!defined('WPINC')) {
    die();
}

use PWSBillingo\PWSBillingo;

class WC_Billingo
{
    const TABLENAME_PARTNERHASH = 'billingo_partnerhash';
    const TABLENAME_DOCUMENTS = 'billingo_documents';

    /** @var bool */
    protected static $initialized = false;

    protected static $flag_rehash_required = false;

    public static function init()
    {
        if (!self::$initialized) {
            if (class_exists('WC_Order')) {
                require_once BILLINGO__PLUGIN_DIR . 'includes/class-billingo-order.php';

                self::$initialized = true;

                add_action('wp_ajax_wc_billingo_generate_invoice', [__CLASS__, 'ajax_generateInvoice']);
                add_action('wp_ajax_wc_billingo_storno_invoice', [__CLASS__, 'ajax_stornoInvoice']);
                add_action('wp_ajax_wc_billingo_already', [__CLASS__, 'wc_billingo_already']);
                add_action('wp_ajax_wc_billingo_already_back', [__CLASS__, 'wc_billingo_already_back']);

                foreach (wc_get_order_statuses() as $status => $name) {
                    $status = str_replace('wc-', '', $status);
                    add_action('woocommerce_order_status_' . $status, [__CLASS__, 'on_order_state_change'], 10);
                }

                add_action('woocommerce_email_before_order_table', [__CLASS__, 'action_woocommerce_email_before_order_table'], 10, 4);

                if (get_option('wc_billingo_vat_number_form') == 'yes') {
                    add_filter('woocommerce_checkout_fields', [__CLASS__, 'add_vat_number_checkout_field']);
                    add_filter('woocommerce_before_checkout_form', [__CLASS__, 'add_vat_number_info_notice']);
                    add_action('woocommerce_checkout_update_order_meta', [__CLASS__, 'save_vat_number']);
                    add_action('woocommerce_admin_order_data_after_billing_address', [__CLASS__, 'display_vat_number']);
                    add_filter('woocommerce_form_field', [__CLASS__, 'remove_checkout_optional_fields_label'], 10, 4);
                }

                if (!get_option('wc_billingo_auto_state')) {
                    update_option('wc_billingo_auto_state', 'wc-completed');
                }
                if (!get_option('wc_billingo_auto_storno')) {
                    update_option('wc_billingo_auto_storno', 'no');
                }

                // fix issue with wp plugin update
                $db_status = get_option('wc_billingo_db_status');
                if ($db_status < 32) {
                    static::install();
                    update_option('wc_billingo_db_status', 32);
                }

                if ($db_status < 33) {
                    update_option('wc_billingo_manual_type', 'invoice');
                    update_option('wc_billingo_db_status', 33);
                }

                $log_status = get_option('wc_billingo_log_status');
                if ($log_status < 4 && file_exists(BILLINGO__PLUGIN_DIR . 'log')) {
                    // relocate log directory
                    rename(BILLINGO__PLUGIN_DIR . 'log', WP_CONTENT_DIR . '/uploads/billingo');
                    update_option('wc_billingo_log_status', 4);
                }

                add_action('before_woocommerce_init', function() { // woocommerce hpos
                    if (class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
                    }
                });
            }
        }
    }

    /**
     * Ajax call for generating documents
     */
    public static function ajax_generateInvoice()
    {
        check_ajax_referer('wc_generate_invoice', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $orderid = (int)$_POST['order'];
        $return_info = static::generateInvoice($orderid);
        wp_send_json_success($return_info);
    }

    /**
     * Ajax call for cancelling documents
     */
    public static function ajax_stornoInvoice()
    {
        check_ajax_referer('wc_storno_invoice', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $order_id = (int)$_POST['order'];
        $response = ['error' => false];

        $order = wc_get_order($order_id);

        if (static::isInvoiceGenerated($order_id, false, false)) {
            static::convertOldInvoiceIdIfNeeded($order_id);
            $invoice_id = static::getInvoiceGenerated($order_id, false, false);
            $invoice_number = static::getInvoiceNumber($invoice_id);

            if ($order->get_meta('_wc_billingo_storno', true) == 1) {
                $order->add_order_note(__('A számla már korábban sztornózásra került.', 'billingo'));
                $response['messages'][] = __('A számla már korábban sztornózásra került.', 'billingo');
            } else {
                if (static::stornoInvoice($invoice_id, $order_id)) {
                    $order->add_order_note(__('Számla sztornózva: ', 'billingo') . $invoice_number);
                    $order->update_meta_data( '_wc_billingo_storno', 1);
                    $order->save();

                    $response['messages'][] = __('Számla sztornózva: ', 'billingo') . $invoice_number;
                } else {
                    $order->add_order_note(__('Nem sikerült sztornózni a számlát: ', 'billingo') . $invoice_number);
                    $response['messages'][] = __('Nem sikerült sztornózni a számlát: ', 'billingo') . $invoice_number;
                }
            }
        }

        wp_send_json_success($response);
    }

    /**
     * Returns and initializes Billingo API Connector
     *
     * @return PWSBillingo
     */
    public static function getBillingoConnector()
    {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        $plugin_data = get_plugin_data(BILLINGO__PLUGIN_DIR . 'index.php');
        $plugin_version = $plugin_data['Version'];

        return new PWSBillingo(get_option('wc_billingo_api_key'), WP_CONTENT_DIR . '/uploads/billingo/billingo_' . date('Y-m-d') . '.txt', 'Wordpress', $plugin_version);
    }

    /**
     * Convert Invoice ID and link to V3 format, if needed.
     */
    public static function convertOldInvoiceIdIfNeeded($id_order)
    {
        $order = wc_get_order($id_order);
        $old_link = $order->get_meta('_wc_billingo_pdf', true);

        if (strpos($old_link, 'http') === false) { // v3 links are full URLs
            // convert ID
            $old_id = $order->get_meta('_wc_billingo_id', true);

            $connector = static::getBillingoConnector();

            $new_id = $connector->convertV2IdToV3($old_id);
            if ($new_id) {
                $order->update_meta_data('_wc_billingo_id', $new_id);

                // convert Link
                $download_link = $connector->getDownloadLinkById($new_id);
                $order->update_meta_data('_wc_billingo_pdf', $download_link);
                $order->save();
            }
        }
    }

    /**
     * Find tax number, if exists, checks order and customer meta fields.
     * @param WC_Order $order
     * @return string
     */
    public static function getTaxNumber($order)
    {
        $id_user = $order->get_user_id();

        $tax_number = '';
        if (get_option('wc_billingo_vat_number_form_checkbox_custom') == 'yes' && ($taxfield = get_option('wc_billingo_vat_number_form_custom'))) { // custom tax number field by other plugins
            if ($adoszam = $order->get_meta($taxfield, true)) {
                $tax_number = $adoszam;
            } elseif ($id_user && ($adoszam = get_user_meta($id_user, $taxfield, true))) {
                $tax_number = $adoszam;
            }
        } elseif ($adoszam = $order->get_meta('adoszam', true)) { // this plugin's tax number field
            $tax_number = $adoszam;
        } elseif ($adoszam = $order->get_meta('_billing_tax_number', true)) { // HuCommerce plugin's tax number field in order
            $tax_number = $adoszam;
        } elseif ($id_user && ($adoszam = get_user_meta($id_user, 'billing_tax_number', true))) { // HuCommerce plugin's tax number field in customer
            $tax_number = $adoszam;
        }

        return sanitize_text_field($tax_number);
    }

    protected static function getProductSKU($item)
    {
        if ($item->get_variation_id()) {
            $product = new WC_Product_Variation($item->get_variation_id());
        } else {
            $product = new WC_Product($item->get_product_id());
        }

        return $product->get_sku();
    }

    protected static function getProductName($item)
    {
        $product_name = $item->get_name();

        if ($item->get_variation_id()) {
            $product = new WC_Product_Variation($item->get_variation_id());

            $variation_attributes = $product->get_variation_attributes();
            $product_attributes = [];
            foreach($variation_attributes as $attribute_taxonomy => $term_slug) {
                $taxonomy = str_replace('attribute_', '', $attribute_taxonomy);
                //$attribute_name = wc_attribute_label($taxonomy, $product);
                if (taxonomy_exists($taxonomy)) {
                    $attribute_value = get_term_by('slug', $term_slug, $taxonomy)->name;
                } else {
                    $attribute_value = $term_slug; // For custom product attributes
                }

                $product_attributes[] = /*$attribute_name . ': ' . */$attribute_value;
            }

            // only needed if there is more than one, woocommerce automatically adds it if there is only one
            if (count($product_attributes) > 1) {
                $product_name .= ' - ' . implode(', ', $product_attributes);
            }
        }

        return $product_name;
    }

    protected static function getPartnerData(WC_Order $order)
    {
        // prepare data
        if (get_option('wc_billingo_flip_name') == 'yes') {
            $name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        } else {
            $name = $order->get_billing_last_name() . ' ' . $order->get_billing_first_name();
        }

        if ($company = trim($order->get_billing_company())) {
            if (get_option('wc_billingo_company_name')) {
                $name = $company;
            } else {
                $name = $company . ' - ' . $name;
            }
        }

        $client_data = [
            'name'     => $name,
            'emails'   => [$order->get_billing_email()],
            'taxcode'  => static::getTaxNumber($order),
            'phone'    => $order->get_billing_phone(),
        ];
        $address_data = [
            'country_code' => $order->get_billing_country() ?: 'HU',
            'post_code'    => $order->get_billing_postcode(),
            'city'         => $order->get_billing_city(),
            'address'      => substr(trim($order->get_billing_address_1() . ' ' . $order->get_billing_address_2()), 0, 125),
        ];

        return [$client_data, $address_data];
    }

    public static function install()
    {
        global $wpdb;

        $wpdb->query('CREATE TABLE IF NOT EXISTS `' . $wpdb->prefix . static::TABLENAME_PARTNERHASH . '` ( 
              `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, 
              `partner_id` INT(11) UNSIGNED NOT NULL, 
              `hash` CHAR(32) NOT NULL, 
              `date_add` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`));');

        $wpdb->query('CREATE TABLE IF NOT EXISTS `' . $wpdb->prefix . static::TABLENAME_DOCUMENTS . '` ( 
              `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT, 
              `order_id` INT(11) UNSIGNED NOT NULL, 
              `billingo_id` INT(11) NULL, 
              `billingo_number` VARCHAR(127) NULL, 
              `link` VARCHAR(255) NULL, 
              `type` VARCHAR(32) NULL, 
              `api_key` VARCHAR(64) NULL, 
              `date_add` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY(`order_id`), 
            KEY(`type`),
            KEY(`api_key`));');
    }

    protected static function findOrCreatePartnerId(WC_Order $order)
    {
        list($client_data, $address_data) = static::getPartnerData($order);

        // check if hash found
        $hash = PWSBillingo::hashPartnerData(get_option('wc_billingo_api_key'), $client_data, $address_data);
        if ($partner_id = static::findPartnerIdByHash($hash)) {
            return $partner_id;
        }

        // check if old hash found
        $old_hash = PWSBillingo::hashPartnerDataOld($client_data, $address_data);

        if ($partner_id = static::findPartnerIdByHash($old_hash)) {
            static::$flag_rehash_required = true;
            return $partner_id;
        }

        //Create client
        $connector = static::getBillingoConnector();

        $partner_id = $connector->createPartnerAndGetId($client_data, $address_data);

        // save hash+id
        if ($partner_id) {
            static::savePartnerIdAndHash($partner_id, $hash);
        }

        return $partner_id;
    }

    public static function findPartnerIdByHash($hash)
    {
        global $wpdb;

        $sql = 'SELECT `partner_id` FROM `' . $wpdb->prefix . static::TABLENAME_PARTNERHASH . '` WHERE `hash` = %s';
        $res = $wpdb->get_row($wpdb->prepare($sql, $hash), ARRAY_A);
        if ($res && isset($res['partner_id']) && ($partner_id = $res['partner_id'])) {
            return $partner_id;
        }

        return false;
    }

    public static function savePartnerIdAndHash($partner_id, $hash)
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . static::TABLENAME_PARTNERHASH, ['partner_id' => $partner_id, 'hash' => $hash]);
    }

    public static function deletePartnerHash($hash)
    {
        global $wpdb;

        $wpdb->delete($wpdb->prefix . static::TABLENAME_PARTNERHASH, ['hash' => $hash]);
    }

    public static function getProductDataArray($name, $unit, $currency, $net_price, $tax, $entitlement, $comment)
    {
        return [
            'name'           => $name,
            'unit'           => $unit,
            'currency'       => $currency,
            'net_unit_price' => $net_price,
            'vat'            => $tax,
            'entitlement'    => $entitlement,
            'comment'        => $comment,
        ];
    }

    public static function getRemoteProductData($id_product)
    {
        $remote_id = get_post_meta($id_product, '_wc_billingo_remote_id', true);
        if ($remote_id) {
            return [
                'id' => $remote_id,
                'data' => get_post_meta($id_product, '_wc_billingo_last_remote_data', true) ?: [],
            ];
        }

        return [
            'id' => $remote_id,
            'data' => [],
        ];
    }

    protected static function getRemoteProductId($id_product, $data)
    {
        $found_data = static::getRemoteProductData($id_product);
        $connector = static::getBillingoConnector();

        if ($found_data['id']) {
            $diff = !count($found_data['data']); // if data array is empty, we need an update on an existing remote product (that was manually linked)
            if ($diff) {
                $connector->logw('Existing product without data stored, update required. #' . $id_product . ' ' . $data['name']);
            } else {
                foreach ($data as $field => $value) {
                    if ($value != $found_data['data'][$field]) {
                        $connector->logw('Existing product data difference, stored ' . $field . ': ' . $found_data['data'][$field] . ', new: ' . $value);
                        $diff = true;
                    }
                }
                if ($diff) {
                    $connector->logw('Existing product with data stored, data mismatch, update required. #' . $id_product . ' ' . $data['name']);
                }
            }

            if ($diff) {
                $connector->updateProduct($found_data['id'], $data);
                static::saveRemoteProductData($id_product, $found_data['id'], $data);
            }

            $connector->logw('Existing product found for #' . $id_product . ' ' . $data['name'] . ': ' . $found_data['id']);
            return $found_data['id'];
        }

        $connector->logw('Existing product not found, creating. #' . $id_product . ' ' . $data['name']);
        $remote_product_id = $connector->createProductAndGetId($data);

        if ($remote_product_id) {
            static::saveRemoteProductData($id_product, $remote_product_id, $data);
        }

        return $remote_product_id;
    }

    public static function saveRemoteProductData($id_product, $remote_id, $remote_data)
    {
        update_post_meta($id_product, '_wc_billingo_remote_id', $remote_id);
        update_post_meta($id_product, '_wc_billingo_last_remote_data', $remote_data);
    }

    public static function saveInvoiceData($type, $order_id, $billingo_id, $billingo_number, $link)
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . static::TABLENAME_DOCUMENTS, [
            'order_id' => $order_id,
            'billingo_id' => $billingo_id,
            'billingo_number' => $billingo_number,
            'link' => $link,
            'type' => $type,
            'api_key' => get_option('wc_billingo_api_key', ''),
        ]);
    }

    public static function findOrderInvoice($order_id, $type)
    {
        global $wpdb;

        $sql = 'SELECT * FROM `' . $wpdb->prefix . static::TABLENAME_DOCUMENTS . '` WHERE `order_id` = %s AND `type` = %s AND `api_key` = %s';
        $row = $wpdb->get_row($wpdb->prepare($sql, [$order_id, $type, get_option('wc_billingo_api_key', '')]), ARRAY_A);

        if ($row && $row['billingo_id']) {
            return $row;
        }

        $order = wc_get_order($order_id);

        // old data
        switch ($type) {
            case PWSBillingo::INVOICE_TYPE_STORNO: $ret = [
                    'type' => PWSBillingo::INVOICE_TYPE_STORNO,
                    'billingo_id' => 0,
                    'link' => $order->get_meta('_wc_billingo_storno_pdf', true),
                    'billingo_number' => $order->get_meta('_wc_billingo_storno_invoice_number', true),
                ];
                break;
            case PWSBillingo::INVOICE_TYPE_DRAFT: $ret = [
                    'type' => PWSBillingo::INVOICE_TYPE_DRAFT,
                    'billingo_id' => $order->get_meta('_wc_billingo_piszkozat_id', true),
                    'link' => $order->get_meta('_wc_billingo_piszkozat_pdf', true),
                    'billingo_number' => $order->get_meta('_wc_billingo_piszkozat', true),
                ];
                break;
            case PWSBillingo::INVOICE_TYPE_PROFORMA: $ret =  [
                    'type' => PWSBillingo::INVOICE_TYPE_PROFORMA,
                    'billingo_id' => $order->get_meta('_wc_billingo_dijbekero_id', true),
                    'link' => $order->get_meta('_wc_billingo_dijbekero_pdf', true),
                    'billingo_number' => $order->get_meta('_wc_billingo_dijbekero', true),
                ];
                break;
            default: $ret = [
                    'type' => PWSBillingo::INVOICE_TYPE_INVOICE,
                    'billingo_id' => $order->get_meta('_wc_billingo_id', true),
                    'link' => $order->get_meta('_wc_billingo_pdf', true),
                    'billingo_number' => $order->get_meta('_wc_billingo', true),
                ];
        }

        // if found, save old data as new with api key, if we can fetch invoice_number (means it was generated with current api key
        if ($ret['billingo_number']) {
            $invoice_number = static::getInvoiceNumber($ret['billingo_id']);
            if ($invoice_number) {
                static::saveInvoiceData($ret['type'], $order_id, $ret['billingo_id'], $ret['billingo_number'], $ret['link']);
            } else {
                $connector = static::getBillingoConnector();
                $connector->logw('Could not get invoice number with current API Key for invoice', $ret);
            }
        }

        return $ret;
    }

    protected static function processLock($id_order)
    {
        $order = wc_get_order($id_order);
        $lock = (int)$order->get_meta('_wc_billingo_lock', true);
        if ($lock && (strtotime($lock) > strtotime('-10 minutes'))) {
            return [
                'error' => true,
                'messages' => [
                    'LOCK: Generation in progress (wait 10 minutes)'
                ]
            ];
        }

        $order->update_meta_data('_wc_billingo_lock', date('Y-m-d H:i:s'));
        $order->save();
        register_shutdown_function(function ($order) {
            $order->delete_meta_data('_wc_billingo_lock');
            $order->save();
        }, $order);

        return false;
    }

    protected static function findExistingProformaId($id_order)
    {
        $existing_proforma = static::findOrderInvoice($id_order, PWSBillingo::INVOICE_TYPE_PROFORMA);
        if (is_array($existing_proforma) && array_key_exists('billingo_id', $existing_proforma) && $existing_proforma['billingo_id']) {
            return $existing_proforma['billingo_id'];
        }

        return false;
    }

    /**
     * Generates invoice, draft or proforma type documents
     *
     * @param int $id_order ID of the order that needs to be invoiced
     * @param bool $payment_request Tells if the document type should be proforma
     *
     * @return array $response
     */
    public static function generateInvoice($id_order, $payment_request = false)
    {
        $is_draft = false;

        // determine invoice type
        if ($payment_request) {
            $invoice_type = (get_option('wc_billingo_payment_request_auto') == 'draft') ? PWSBillingo::INVOICE_TYPE_DRAFT : PWSBillingo::INVOICE_TYPE_PROFORMA;
        } else {
            $invoice_type = (get_option('wc_billingo_auto') == 'draft') ? PWSBillingo::INVOICE_TYPE_DRAFT : PWSBillingo::INVOICE_TYPE_INVOICE;
        }

        if (isset($_POST['invoice_type']) && $_POST['invoice_type'] && in_array($_POST['invoice_type'], PWSBillingo::INVOICE_TYPE_LIST)) {
            $invoice_type = sanitize_text_field($_POST['invoice_type']); // casually waste CPU cycles on the sanitizer after the enum check :)
        }

        switch ($invoice_type) {
            case PWSBillingo::INVOICE_TYPE_DRAFT: $is_draft = true; break;
            case PWSBillingo::INVOICE_TYPE_PROFORMA: $payment_request = true; break;
        }

        // check if proforma/invoice already exists
        if (($invoice_type == PWSBillingo::INVOICE_TYPE_INVOICE && static::isInvoiceGenerated($id_order)) ||
            ($invoice_type == PWSBillingo::INVOICE_TYPE_PROFORMA && static::isInvoiceGenerated($id_order, true))
        ) {
            return [
                'error' => true,
                'messages' => [
                    $invoice_type == PWSBillingo::INVOICE_TYPE_INVOICE ? __('Már létezik a számla.', 'billingo') : __('Már létezik a díjbekérő.', 'billingo')
                ],
            ];
        }


        if ($invoice_type != PWSBillingo::INVOICE_TYPE_INVOICE || get_option('wc_billingo_disable_proforma_invoicing') == 'yes' || (isset($_POST['ignore_proforma']) && $_POST['ignore_proforma'])) {
            // manual gen., ignore prev. proforma
            $existing_proforma_id = false;
        } else {
            $existing_proforma_id = static::findExistingProformaId($id_order);
        }

        $order = new BillingoOrder($id_order);
        $connector = static::getBillingoConnector();

        if (get_option('wc_billingo_block_child_orders') == 'yes' && $order->get_parent_id() != 0) {
            return [
                'error' => true,
                'messages' => [
                    __('Sub-order processing is disabled.', 'billingo')
                ],
            ];
        }

        if ($existing_proforma_id && $invoice_type == PWSBillingo::INVOICE_TYPE_INVOICE) {
            // Invoice generation based on proforma
            $invoice = $connector->createInvoiceFromProforma((int)$existing_proforma_id);
        } else {
            // No proforma found, normal invoice generation

            $billing_country = $order->get_billing_country() ?: 'HU'; //_billing_country => HU

            if ($lock_response = static::processLock($id_order)) {
                return $lock_response;
            }

            if (!($partner_id = static::findOrCreatePartnerId($order))) {
                return [
                    'error' => true,
                    'messages' => [
                        __('Nem sikerült létrehozni az ügyfelet.', 'billingo')
                    ],
                ];
            }

            $order_payment_method = $order->get_payment_method();

            //Get billingo payment method id
            $paymentMethod = get_option('wc_billingo_payment_method_' . $order_payment_method);
            if (!$paymentMethod) {
                $paymentMethod = get_option('wc_billingo_fallback_payment', PWSBillingo::DEFAULT_PAYMENT); // used as fallback
                $order->add_order_note(__('Fizetési mód ismeretlen: ', 'billingo') . $order_payment_method);
            }

            $note = isset($_POST['note']) ? sanitize_text_field($_POST['note']) : get_option('wc_billingo_note');
            $deadline = (isset($_POST['deadline']) && $_POST['deadline'] != '') ? (int)$_POST['deadline'] : ((int)get_option('wc_billingo_paymentdue_' . $order_payment_method));
            $completed_date = wp_date('Y-m-d', (isset($_POST['completed']) && strtotime($_POST['completed'])) ? strtotime($_POST['completed']) : time());


            //Add Barion Transaction ID to note if enabled
            if (get_option('wc_billingo_note_barion') == 'yes' && $barion_id = $order->get_meta('Barion paymentId', true)) {
                $note .= "\n" . __('Barion tranzakció azonosító', 'billingo') . ': ' . sanitize_text_field($barion_id);
            }

            //Add Order ID to note if enabled
            if (get_option('wc_billingo_note_orderid') == 'yes') {
                $note .= "\n" . __('Megrendelés azonosító', 'billingo') . ': ' . $order->get_order_number();
            }

            // Set invoice lang
            $lang = get_option('wc_billingo_invoice_lang');
            if (get_option('wc_billingo_invoice_lang_wpml') == 'yes') {
                if ($wpml_lang = $order->get_meta('wpml_language', true)) {
                    $lang = $wpml_lang;
                }
            }
            $lang = strtolower($lang);

            if (!array_key_exists($lang, PWSBillingo::ALL_LANGUAGES)) {
                $lang = 'hu';
            }

            //Mark as paid if needed
            $mark_paid = (!$payment_request && get_option('wc_billingo_mark_as_paid_' . $order_payment_method))
                       || ($payment_request && get_option('wc_billingo_mark_as_paid2_' . $order_payment_method));

            // set tax override variables
            PWSBillingo::setCountryCodeForVat($billing_country);
            PWSBillingo::setEntitlements(get_option('wc_billingo_tax_override_entitlement'), get_option('wc_billingo_tax_override_zero_entitlement'));
            PWSBillingo::setTaxOverrides(
                get_option('wc_billingo_tax_override'),
                get_option('wc_billingo_tax_override_zero'),
                get_option('wc_billingo_tax_override_eu') == 'yes',
                get_option('wc_billingo_tax_override_include_carrier') == 'yes'
            );

            //Create invoce data array
            $invoice_data = [
                'fulfillment_date' => $completed_date,
                'due_date'         => wp_date('Y-m-d', strtotime('+' . $deadline . ' days')),
                'payment_method'   => $paymentMethod,
                'comment'          => $note,
                'language'         => $lang,
                'electronic'       => get_option('wc_billingo_electronic') == 'yes' ? 1 : 0,
                'currency'         => $order->get_currency() ?: 'HUF',
                'partner_id'       => $partner_id,
                'block_id'         => (int)get_option('wc_billingo_invoice_block'),
                'type'             => $invoice_type,
                'paid'             => $mark_paid,
                'settings'         => [
                    'round' => get_option('wc_billingo_invoice_round'),
                    'without_financial_fulfillment' => $mark_paid && get_option('mark_paid_without_financial_fulfillment') == 'yes',
                    'should_send_email' => ($is_draft && get_option('wc_billingo_draft_enable_send') == 'yes') ? 1 : 0
                ],
                'items' => static::createProductItems($order),
            ];

            if ($invoice_data['currency'] == 'HUF' && ($bank_account = get_option('wc_billingo_bank_account_huf'))) {
                $invoice_data['bank_account_id'] = $bank_account;
            } elseif ($invoice_data['currency'] == 'EUR' && ($bank_account = get_option('wc_billingo_bank_account_eur'))) {
                $invoice_data['bank_account_id'] = $bank_account;
            }

            //Create invoice
            $invoice = $connector->createInvoice(apply_filters('wc_billingo_invoicedata', $invoice_data, $order));
        }

        if ($connector->last_response_code == 403 && static::$flag_rehash_required) {
            // if we got rejected, and used old hash, try re-creating the partner and repeat
            list($client_data, $address_data) = static::getPartnerData($order);

            // hash
            $old_hash = PWSBillingo::hashPartnerDataOld($client_data, $address_data);
            $hash = PWSBillingo::hashPartnerData(get_option('wc_billingo_api_key'), $client_data, $address_data);

            $partner_id = $connector->createPartnerAndGetId($client_data, $address_data);

            static::deletePartnerHash($old_hash);
            static::savePartnerIdAndHash($partner_id, $hash);
            static::$flag_rehash_required = false;

            // retry invoice with new partner ID
            $invoice_data['partner_id'] = $partner_id;
            $invoice = $connector->createInvoice(apply_filters('wc_billingo_invoicedata', $invoice_data, $order));
        }

        $response = ['error' => false];

        if (!is_array($invoice) || !array_key_exists('id', $invoice)) {
            $response['error'] = true;
            $response['messages'][] = __('Nem sikerült létrehozni a számlát.', 'billingo');
            $order->add_order_note(__('Billingo számlakészítés sikertelen!', 'billingo'));
            return $response;
        }

        if (static::$flag_rehash_required) {
            // if invoice was made successfully, resave partner with new hash
            list($client_data, $address_data) = static::getPartnerData($order);

            // hash
            $old_hash = PWSBillingo::hashPartnerDataOld($client_data, $address_data);
            $hash = PWSBillingo::hashPartnerData(get_option('wc_billingo_api_key'), $client_data, $address_data);

            static::deletePartnerHash($old_hash);
            static::savePartnerIdAndHash($partner_id, $hash);
        }

        //Create download link
        if (!($doc_public_url = $connector->getDownloadLinkById($invoice['id']))) {
            $response['messages'][] = __('Nem sikerült létrehozni a letöltési linket a számlához.', 'billingo');
            $doc_public_url = '';
        }

        //Send via email if needed
        if (!$is_draft && ($payment_request && in_array(get_option('wc_billingo_proforma_email'), ['yes', 'both'])) || (!$payment_request && in_array(get_option('wc_billingo_email'), ['yes', 'both']))) {
            if (!$connector->sendInvoice($invoice['id'])) {
                $response['messages'][] = __('Nem sikerült elküldeni emailben a számlát', 'billingo');
            }
        }

        //Create response
        $szamlaszam = $invoice['invoice_number'] ?: $invoice['id'];
        $response['invoice_name'] = $szamlaszam;

        //Save data
        static::saveInvoiceData($invoice_type, $id_order, $invoice['id'], $invoice['invoice_number'], $doc_public_url);
        switch ($invoice_type) {
            case PWSBillingo::INVOICE_TYPE_DRAFT:
                $response['messages'][] = __('Piszkozat sikeresen létrehozva.', 'billingo');
                $order->add_order_note(__('Billingo piszkozat sikeresen létrehozva. A sorszáma: ', 'billingo') . $szamlaszam);
                $response['link'] = '<p><a href="' . esc_url(static::generateDownloadLink($id_order, PWSBillingo::INVOICE_TYPE_DRAFT)) . '" id="wc_billingo_download" class="button button-primary" target="_blank">' . __('Piszkozat megtekintése', 'billingo') . '</a></p>';
                break;
            case PWSBillingo::INVOICE_TYPE_PROFORMA:
                $response['messages'][] = __('Díjbekérő sikeresen létrehozva.', 'billingo');
                $order->add_order_note(__('Billingo díjbekérő sikeresen létrehozva. A díjbekérő sorszáma: ', 'billingo') . $szamlaszam);
                $response['link'] = '<p><a href="' . esc_url(static::generateDownloadLink($id_order, PWSBillingo::INVOICE_TYPE_PROFORMA)) . '" id="wc_billingo_download" class="button button-primary" target="_blank">' . __('Díjbekérő megtekintése', 'billingo') . '</a></p>';
                break;
            case PWSBillingo::INVOICE_TYPE_INVOICE:
                $response['messages'][] = __('Számla sikeresen létrehozva.', 'billingo');
                $order->add_order_note(__('Billingo számla sikeresen létrehozva. A számla sorszáma: ', 'billingo') . $szamlaszam);
                $response['link'] = '<p><a href="' . esc_url(static::generateDownloadLink($id_order, PWSBillingo::INVOICE_TYPE_INVOICE)) . '" id="wc_billingo_download" class="button button-primary" target="_blank">' . __('Számla megtekintése', 'billingo') . '</a></p>';
                break;
        }

        return $response;
    }

    /**
     * Constructs the product items
     *
     * @param BillingoOrder $order (WC_Order with a tax location bypass)
     *
     * @return array product_items
     */
    public static function createProductItems($order)
    {
        $default_unit = __('db', 'billingo');
        $unit = get_option('wc_billingo_unit') ? __(get_option('wc_billingo_unit'), 'billingo') : $default_unit;
        $use_net_price = (bool)get_option('wc_billingo_pricing');
        $unit_price_type = $use_net_price ? PWSBillingo::PRICE_TYPE_NET : PWSBillingo::PRICE_TYPE_GROSS;
        $add_sku = get_option('wc_billingo_sku') == 'yes';
        $coupon_objs = static::getCouponObjectsForOrder($order);
        $order_tax_location = $order->get_tax_location_modified();

        $product_items = [];

        foreach ($order->get_items() as $item) {
            // Can't use line_tax directly because of the rounding issue, this should solve it. (product with gross 10 huf and 5% tax shows 0 in line_tax, should be 0.476.)
            $line_tax = 0.0;
            foreach ($item['line_tax_data']['total'] as $tax_id => $tax_amount) {
                $line_tax += $tax_amount;
            }

            if ($line_tax) { // should solve is_vat_exempt issue, bypass the normal tax class if no tax was used
                $tax_rate = 0;
                $tax_rates = static::get_rates($item->get_tax_class(), $order_tax_location);
                if (!empty($tax_rates)) {
                    $tax_rate = reset($tax_rates);
                    $tax_rate = $tax_rate['rate'];
                }

                $vat_rule = PWSBillingo::applyVatRule($tax_rate, false);
            } else {
                $vat_rule = PWSBillingo::applyVatRule(0, false);
            }

            $erase_code = get_option('wc_billingo_is_generate_erase_code', false);
            $erase_code_value = false;

            if ($erase_code && $order->get_meta($erase_code, true)) {
                $erase_code_value = $order->get_meta($erase_code, true);
            }

            $product_item = [
                'unit'            => $unit,
                'name'            => static::getProductName($item),
                'quantity'        => $item->get_quantity(),
                'vat'             => $vat_rule['vat'],
                'entitlement'     => $vat_rule['entitlement'],
                'unit_price'      => round($order->get_item_total($item, !$use_net_price, false), 6),
                'unit_price_type' => $unit_price_type,
                'comment'         => $add_sku ? (__('Cikkszám', 'billingo') . ': ' . static::getProductSKU($item)) : '',
                'is_generate_erase_code' => $erase_code_value,
            ];

            $discount = $order->get_item_subtotal($item, false, false) - $order->get_item_total($item, false, false);
            if ($discount) {
                $prod = new WC_Product($item->get_product_id());
                $codes = [];
                foreach ($coupon_objs as $coupon) {
                    if ($coupon->is_valid_for_cart() || $prod->get_id() && $coupon->is_valid_for_product($prod)) {
                        $codes[] = $coupon->get_code();
                    }
                }

                if (isset($product_item['comment'])) {
                    $product_item['comment'] .= ', ';
                }
                $product_item['comment'] .= __('Kedvezmény', 'billingo') . ': ' . round($discount, $order->get_currency() == 'HUF' ? 0 : 2) . ' ' . $order->get_currency() . ' (' . implode(', ', $codes) . ')';
            }

            if (get_option('wc_billingo_use_product_id')) {
                $product_data = static::getProductDataArray($product_item['name'], $unit, $order->get_currency(), round($order->get_item_total($item, false, false), 6), $vat_rule['vat'], $vat_rule['entitlement'], $product_item['comment']);
                $id_product_remote = static::getRemoteProductId($item->get_variation_id() ?: $item->get_product_id(), $product_data);
                if ($id_product_remote) {
                    $product_item = [
                        'product_id' => $id_product_remote,
                        'quantity' => $product_item['quantity'],
                        'comment' => $product_item['comment'],
                    ];
                }
            }


            $product_items[] = $product_item;
        }

        //Shipping
        $always_add_shipping = get_option('wc_billingo_always_add_carrier', 'no') == 'yes';
        if ($shipping_items = $order->get_shipping_methods()) {
            $item = reset($shipping_items);

            $order_shipping = method_exists($order, 'get_shipping_total') ? $order->get_shipping_total() : $order->order_shipping;
            $order_shipping_tax = method_exists($order, 'get_shipping_tax') ? $order->get_shipping_tax() : $order->order_shipping_tax;

            if ($order_shipping > 0 || $always_add_shipping) {
                if ($order_shipping_tax > 0) {
                    $tax_rate = 0;

                    if (isset($item['taxes']) && ($taxes = $item->get_taxes())) {
                        if (array_key_exists('total', $taxes)) {
                            $tax_list = $taxes['total'];
                            if (count($tax_list) > 1) { // fix for foxpost module having an empty 5% tax entry for 27% shipping
                                foreach ($tax_list as $k => $v) {
                                    if (!$v) {
                                        unset($tax_list[$k]);
                                    }
                                }
                            }
                            $tax_ids = array_keys($tax_list);
                            $tax_rate_id = reset($tax_ids);
                            if ($tax_rate_id) {
                                $tax_rate = floatval(WC_Tax::get_rate_percent($tax_rate_id));
                            }
                        }
                    }

                    if (!$tax_rate) {
                        $tax_rates = self::get_shipping_tax_rates($order_tax_location, $item->get_tax_class());
                        if (!empty($tax_rates)) {
                            $tax_rate = reset($tax_rates);
                            $tax_rate = $tax_rate['rate'];
                        }
                    }

                    $vat_rule = PWSBillingo::applyVatRule($tax_rate, true);
                } else {
                    $vat_rule = PWSBillingo::applyVatRule(0, true);
                }

                if ($order_shipping > 0) {
                    $shipping_total = $order_shipping_tax + $order_shipping;
                    $shipping_net_total = $order_shipping;
                } else {
                    $shipping_total = 0;
                    $shipping_net_total = 0;
                }

                $product_item = [
                    'unit'            => $default_unit,
                    'name'            => $order->get_shipping_method(),
                    'quantity'        => 1,
                    'vat'             => $vat_rule['vat'],
                    'entitlement'     => $vat_rule['entitlement'],
                    'unit_price'      => $use_net_price ? $shipping_net_total : $shipping_total,
                    'unit_price_type' => $unit_price_type,
                ];

                $product_items[] = $product_item;
            }
        } elseif ($always_add_shipping) {
            $vat_rule = PWSBillingo::applyVatRule(0, true);
            $product_item = [
                'unit'            => $default_unit,
                'name'            => __('Szállítás', 'billingo'),
                'quantity'        => 1,
                'vat'             => $vat_rule['vat'],
                'entitlement'     => $vat_rule['entitlement'],
                'unit_price'      => 0,
                'unit_price_type' => $unit_price_type,
            ];

            $product_items[] = $product_item;
        }

        //Extra Fees
        $fees = $order->get_fees();
        if (!empty($fees)) {
            foreach ($fees as $fee) {
                $gross_price = $fee['line_tax'] + $fee['line_total'];
                if (isset($fee['line_total']) && abs(intval($fee['line_total'])) > 0) {
                    $tax_rate = abs(floatval($fee['line_tax']) / floatval($fee['line_total']) * 100);
                    $vat_rule = PWSBillingo::applyVatRule($tax_rate, false);
                } else {
                    $vat_rule = PWSBillingo::applyVatRule(0, false);
                }

                $product_item = [
                    'unit'            => $default_unit,
                    'name'            => $fee['name'],
                    'quantity'        => 1,
                    'vat'             => $vat_rule['vat'],
                    'entitlement'     => $vat_rule['entitlement'],
                    'unit_price'      => floatval($use_net_price ? $fee['line_total'] : $gross_price),
                    'unit_price_type' => $unit_price_type,
                ];

                $product_items[] = $product_item;
            }
        }

        return $product_items;
    }

    /**
     * Modified version of WC_Tax::get_rates to work with order address
     * @param string $tax_class
     * @param array $location
     * @return mixed|void
     */
    public static function get_rates($tax_class = '', $location = []) {
        $tax_class = sanitize_title( $tax_class );

        if (!$location) {
            $matched_tax_rates = [];
        } else {
            $matched_tax_rates = WC_Tax::find_rates([
                'country' => $location['country'],
                'state' => $location['state'],
                'postcode' => $location['postcode'],
                'city' => $location['city'],
                'tax_class' => $tax_class,
            ]);
        }

        return apply_filters('woocommerce_matched_rates', $matched_tax_rates, $tax_class);
    }

    // part of WC_Tax::get_shipping_tax_rates
    public static function get_shipping_tax_rates($location, $tax_class = null)
    {
        // See if we have an explicitly set shipping tax class
        $shipping_tax_class = get_option('woocommerce_shipping_tax_class');

        if ('inherit' !== $shipping_tax_class) {
            $tax_class = $shipping_tax_class;
        }

        $matched_tax_rates = [];

        if (sizeof($location) === 4) {
            $country = $location['country'];
            $state = $location['state'];
            $postcode = $location['postcode'];
            $city = $location['city'];

            if (!is_null($tax_class)) {
                // This will be per item shipping
                $matched_tax_rates = WC_Tax::find_shipping_rates([
                    'country' => $country,
                    'state' => $state,
                    'postcode' => $postcode,
                    'city' => $city,
                    'tax_class' => $tax_class,
                ]);

            }

            // Get standard rate if no taxes were found
            if (!sizeof($matched_tax_rates)) {
                $matched_tax_rates = WC_Tax::find_shipping_rates([
                    'country' => $country,
                    'state' => $state,
                    'postcode' => $postcode,
                    'city' => $city,
                ]);
            }
        }

        return $matched_tax_rates;
    }

    /**
     * Cancels documents
     *
     * @param integer $id_invoice ID of the invoice that needs to be canceled
     * @param integer $id_order ID of the order that is linked to the invoice
     *
     * @return boolean
     */
    public static function stornoInvoice($id_invoice, $id_order)
    {
        $connector = static::getBillingoConnector();
        $invoice = $connector->cancelInvoice($id_invoice);

        if (is_array($invoice) && array_key_exists('id', $invoice) && $invoice['id']) {
            //Create download link
            $doc_public_url = $connector->getDownloadLinkById($invoice['id']);
            if ($doc_public_url) {
                static::saveInvoiceData(PWSBillingo::INVOICE_TYPE_STORNO, $id_order, $invoice['id'], $invoice['invoice_number'], $doc_public_url);
            } else {
                return false;
            }

            //Send via email if needed
            if (in_array(get_option('wc_billingo_storno_email'), ['yes', 'both'])) {
                if (!$connector->sendInvoice($invoice['id'])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Processes automatic document operations
     *
     * @param integer $order_id ID of the order that is linked to the document
     * @throws Exception
     */
    public static function on_order_state_change($order_id)
    {
        $order = wc_get_order($order_id);
        $status = $order->get_status();

        $invoice_status = str_replace('wc-', '', get_option('wc_billingo_auto_state')) ?: 'completed';
        $storno_status = str_replace('wc-', '', get_option('wc_billingo_auto_storno')) ?: 'no';

        //if auto-invoice enabled & order-state matches (& invoice doesn't already exist): do invoice
        if ((get_option('wc_billingo_auto') == 'yes' || get_option('wc_billingo_auto') == 'draft') && $status == $invoice_status) {
            if (!static::isInvoiceGenerated($order_id, false, get_option('wc_billingo_auto') == 'draft')) {
                static::generateInvoice($order_id);
            }
        }

        //if auto-storno enabled && order state matches && invoice or proforma exists: do storno
        if ($storno_status != 'no' && $status == $storno_status && static::isInvoiceGenerated($order_id, false, false)) {
            static::convertOldInvoiceIdIfNeeded($order_id);
            $invoice_id = static::getInvoiceGenerated($order_id, false, false);
            if ($order->get_meta('_wc_billingo_storno', true) == 1) {
                $order->add_order_note(__('A számla már korábban sztornózásra került.', 'billingo'));
            } else {
                if (static::stornoInvoice($invoice_id, $order_id)) {
                    $order->add_order_note(__('Számla sztornózva: ', 'billingo') . $invoice_id);
                    $order->update_meta_data('_wc_billingo_storno', 1);
                    $order->save();
                } else {
                    $order->add_order_note(__('Nem sikerült sztornózni a számlát: ', 'billingo') . $invoice_id);
                }
            }
        }

        //Only generate invoice, if it wasn't already generated & only if automatic invoice is enabled
        if (get_option('wc_billingo_payment_request_auto') == 'yes' || get_option('wc_billingo_payment_request_auto') == 'draft') {
            if (!static::isInvoiceGenerated($order_id, true, get_option('wc_billingo_payment_request_auto') == 'draft')) {
                $order_payment_method = $order->get_payment_method();

                if (get_option('wc_billingo_proforma_' . $order_payment_method)) {
                    static::generateInvoice($order_id, true);
                }
            }
        }
    }

    /**
     * Checks if document is already generated
     *
     * @param integer $order_id ID of the order that is linked to the document
     * @param boolean $check_proforma checks if the document type is proforma
     * @param boolean $check_draft checks if the document type is draft
     *
     * @return boolean
     */
    public static function isInvoiceGenerated($order_id, $check_proforma = false, $check_draft = false)
    {
        $document_invoice = static::findOrderInvoice($order_id, PWSBillingo::INVOICE_TYPE_INVOICE);
        if (is_array($document_invoice) && array_key_exists('billingo_id', $document_invoice) && $document_invoice['billingo_id']) {
            return true;
        }
        $order = wc_get_order($order_id);
        if ($order->get_meta('_wc_billingo_own', true)) {
            return true;
        }

        if ($check_proforma && static::isProformaGenerated($order_id)) {
            return true;
        }

        if ($check_draft) {
            $document_draft = static::findOrderInvoice($order_id, PWSBillingo::INVOICE_TYPE_DRAFT);
            if (is_array($document_draft) && array_key_exists('billingo_id', $document_draft) && $document_draft['billingo_id']) {
                return true;
            }
        }

        return false;
    }

    public static function isProformaGenerated($order_id)
    {
        $document_proforma = static::findOrderInvoice($order_id, PWSBillingo::INVOICE_TYPE_PROFORMA);
        if (is_array($document_proforma) && array_key_exists('billingo_id', $document_proforma) && $document_proforma['billingo_id']) {
            return true;
        }

        return false;
    }

    /**
     * Gets generated document ID
     *
     * @param integer $order_id ID of the order that is linked to the document
     * @param boolean $check_proforma checks if the document type is proforma
     * @param boolean $check_draft checks if the document type is draft
     *
     * @return string
     */
    public static function getInvoiceGenerated($order_id, $check_proforma = false, $check_draft = false)
    {
        $document_invoice = static::findOrderInvoice($order_id, PWSBillingo::INVOICE_TYPE_INVOICE);
        if (is_array($document_invoice) && array_key_exists('billingo_id', $document_invoice) && $document_invoice['billingo_id']) {
            return $document_invoice['billingo_id'];
        }

        if ($check_proforma) {
            $document_proforma = static::findOrderInvoice($order_id, PWSBillingo::INVOICE_TYPE_PROFORMA);
            if (is_array($document_proforma) && array_key_exists('billingo_id', $document_proforma) && $document_proforma['billingo_id']) {
                return $document_proforma['billingo_id'];
            }
        }

        if ($check_draft) {
            $document_draft = static::findOrderInvoice($order_id, PWSBillingo::INVOICE_TYPE_DRAFT);
            if (is_array($document_draft) && array_key_exists('billingo_id', $document_draft) && $document_draft['billingo_id']) {
                return $document_draft['billingo_id'];
            }
        }

        return false;
    }

    /**
     * Gets generated document Number
     *
     * @param integer $order_id ID of the order that is linked to the document
     * @param boolean $check_proforma checks if the document type is proforma
     * @param boolean $check_draft checks if the document type is draft
     *
     * @return string
     */
    public static function getInvoiceNumberGenerated($order_id, $check_proforma = false, $check_draft = false)
    {
        $document_invoice = static::findOrderInvoice($order_id, PWSBillingo::INVOICE_TYPE_INVOICE);
        if (is_array($document_invoice) && array_key_exists('billingo_number', $document_invoice) && $document_invoice['billingo_number']) {
            return $document_invoice['billingo_number'];
        }

        if ($check_proforma) {
            $document_proforma = static::findOrderInvoice($order_id, PWSBillingo::INVOICE_TYPE_PROFORMA);
            if (is_array($document_proforma) && array_key_exists('billingo_number', $document_proforma) && $document_proforma['billingo_number']) {
                return $document_proforma['billingo_number'];
            }
        }

        if ($check_draft) {
            $document_draft = static::findOrderInvoice($order_id, PWSBillingo::INVOICE_TYPE_DRAFT);
            if (is_array($document_draft) && array_key_exists('billingo_number', $document_draft) && $document_draft['billingo_number']) {
                return $document_draft['billingo_number'];
            }
        }

        return false;
    }

    /**
     * Gets generated document number
     *
     * @param integer $invoice_id ID of the document in question
     *
     * @return string
     */
    public static function getInvoiceNumber($invoice_id)
    {
        if (!$invoice_id) {
            return false;
        }

        $connector = static::getBillingoConnector();

        return $connector->getInvoiceNumberById($invoice_id);
    }

    /**
     * Generates download url
     *
     * @param integer $order_id ID of the order that is linked to the document
     * @param string $type document type
     *
     * @return string|false
     */
    public static function generateDownloadLink($order_id, $type = PWSBillingo::INVOICE_TYPE_INVOICE)
    {
        if ($order_id) {
            $document = static::findOrderInvoice($order_id, $type);
            if (is_array($document) && array_key_exists('link', $document) && $document['link']) {
                return PWSBillingo::getV2FixedUrl($document['link']);
            }
        }

        return false;
    }

    /**
     * Gets enabled payment gateways (WooCommerce)
     *
     * @return array
     */
    public static function get_available_payment_methods()
    {
        $available_gateways = WC()->payment_gateways->payment_gateways();
        $available = [];
        foreach ($available_gateways as $available_gateway) {
            if ($available_gateway->enabled == 'yes') {
                $available[$available_gateway->id] = $available_gateway->title;
            }
        }
        return $available;
    }

    /**
     * If the invoice is already generated without the plugin
     */
    public static function wc_billingo_already()
    {
        check_ajax_referer('wc_already_invoice', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $order_id = (int)$_POST['order'];
        $order = wc_get_order($order_id);
        $note = sanitize_text_field($_POST['note']);
        $order->update_meta_data('_wc_billingo_own', $note);
        $order->save();

        $response = [
            'error'        => false,
            'messages'     => [__('Saját számla sikeresen hozzáadva.', 'billingo')],
            'invoice_name' => $note,
        ];
        wp_send_json_success($response);
    }

    /**
     * If the invoice is already generated without the plugin, turns it off
     */
    public static function wc_billingo_already_back()
    {
        check_ajax_referer('wc_already_invoice', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $order_id = (int)$_POST['order'];
        $order = wc_get_order($order_id);
        $order->update_meta_data('_wc_billingo_own', '');
        $order->save();

        $response = [
            'error' => false,
            'messages' => [__('Visszakapcsolás sikeres.', 'billingo')],
        ];
        wp_send_json_success($response);
    }

    /**
     * Adds vat number field to checkout page
     *
     * @param array $fields of the checkout page
     *
     * @return array
     */
    public static function add_vat_number_checkout_field($fields)
    {
        $fields['billing']['adoszam'] = [
            'label'       => __('Adószám (magyar adóalanyok esetében kötelező)', 'billingo'),
            'placeholder' => _x('12345678-1-23', 'placeholder', 'billingo'),
            'required'    => false,
            'class'       => ['form-row-wide'],
            'clear'       => true
        ];

        return $fields;
    }

    /**
     * Adds vat number field to checkout page
     *
     * @param string $field
     * @param string $key
     * @param $args
     * @param $value
     * @return string
     */
    public static function remove_checkout_optional_fields_label($field, $key, $args, $value)
    {
        // Only on checkout page
        if (is_checkout() && !is_wc_endpoint_url() && $key == 'adoszam') {
            $optional = '&nbsp;<span class="optional">(' . esc_html__('optional', 'woocommerce') . ')</span>';
            $field = str_replace($optional, '', $field);
        }

        return $field;
    }

    public static function add_vat_number_info_notice($checkout)
    {
        if ($text = trim(get_option('wc_billingo_vat_number_notice'))) {
            wc_print_notice($text, 'notice');
        }
    }

    /**
     * Saves VAT number for the order
     *
     * @param integer $order_id ID of the order in question
     */
    public static function save_vat_number($order_id)
    {
        if (!empty($_POST['adoszam'])) {
            $order = wc_get_order($order_id);
            $order->update_meta_data('adoszam', sanitize_text_field($_POST['adoszam']));
            $order->save();
        }
    }

    /**
     * Displays VAT number in the admin area
     *
     * @param object $order ID of the order in question
     */
    public static function display_vat_number($order)
    {
        if ($adoszam = $order->get_meta('adoszam', true)) {
            echo('<p><strong>' . __('Adószám') . ':</strong> ' . esc_html($adoszam) . '</p>');
        }
    }

    /**
     * Extends order notification e-mail with document link
     *
     * @param object $order
     * @param boolean $sent_to_admin
     * @param string $plain_text
     * @param boolean $email
     */
    public static function action_woocommerce_email_before_order_table(
        $order,
        $sent_to_admin,
        $plain_text, $email = false)
    {
        if (!$email) {
            return;
        }

        $text = '';
        $btn_text = '';
        $pdf_link = false;
        $email_id = $email->id;

        // todo log email type

        // invoice
        if (in_array(get_option('wc_billingo_email'), ['attach', 'both']) && in_array($email_id, ['customer_completed_order', 'customer_completed_renewal_order', 'customer_completed_switch_order'])) {
            $pdf_link = static::generateDownloadLink($order->get_id(), PWSBillingo::INVOICE_TYPE_INVOICE);

            $text = get_option('wc_billingo_email_woo_text', __('Számlája elkészült, melyet az alábbi linken tud letölteni.'));
            $btn_text = get_option('wc_billingo_email_woo_btn', __('Számla letöltése'));
        }

        // storno
        if (in_array(get_option('wc_billingo_storno_email'), ['attach', 'both']) && $email_id == 'customer_refunded_order') {
            $pdf_link = static::generateDownloadLink($order->get_id(), PWSBillingo::INVOICE_TYPE_STORNO);

            $text = get_option('wc_billingo_storno_email_woo_text', __('Storno számlája elkészült, melyet az alábbi linken tud letölteni.'));
            $btn_text = get_option('wc_billingo_storno_email_woo_btn', __('Storno számla letöltése'));
        }

        // proforma
        if (in_array(get_option('wc_billingo_proforma_email'), ['attach', 'both']) && in_array($email_id, ['customer_processing_order', 'customer_on_hold_order'])) {
            $pdf_link = static::generateDownloadLink($order->get_id(), PWSBillingo::INVOICE_TYPE_PROFORMA);

            $text = get_option('wc_billingo_proforma_email_woo_text', __('Díjbekérője elkészült, melyet az alábbi linken tud letölteni.'));
            $btn_text = get_option('wc_billingo_proforma_email_woo_btn', __('Díjbekérő letöltése'));
        }

        if (!$pdf_link) {
            return;
        }

        $str = '<p class="billingo-p">' . esc_html($text) . '<br /><a href="' . esc_url($pdf_link) . '" class="billingoButton" target="_blank">' . esc_html($btn_text) . '</a></p>
<style>
    .billingoButton { background-color:#77b55a; -moz-border-radius:4px; -webkit-border-radius:4px; border-radius:4px; border:1px solid #4b8f29; display:inline-block; cursor:pointer; color:#ffffff; font-family:Arial; font-size:18px; font-weight:bold; 
    padding:16px 24px !important; text-decoration:none; text-shadow:0px 1px 0px #5b8a3c; margin-top: 12px;} 
    .billingoButton:hover { background-color:#72b352; } 
    .billingoButton:active { position:relative; top:1px; }
</style>';

        echo($str);
    }

    /**
     * Gets order's coupons
     *
     * @param WC_Abstract_Order $order
     *
     * @return array
     */
    protected static function getCouponObjectsForOrder(WC_Abstract_Order $order)
    {
        global $woocommerce;

        $coupon_objs = [];

        if (version_compare($woocommerce->version, '3.7.0', '<')) {
            $order_coupons = $order->get_used_coupons();
        } else {
            $order_coupons = $order->get_coupon_codes();
        }

        foreach ($order_coupons as $coupon_code) {
            $coupon_post_obj = get_page_by_title($coupon_code, OBJECT, 'shop_coupon');
            $coupon_id = $coupon_post_obj->ID;

            $coupon = new WC_Coupon($coupon_id);
            $coupon_objs[] = $coupon;
        }

        return $coupon_objs;
    }
}
