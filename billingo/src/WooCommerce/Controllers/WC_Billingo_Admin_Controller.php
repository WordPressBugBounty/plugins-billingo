<?php

namespace App\Billingo\WooCommerce\Controllers;

use App\Billingo\Enums\DeleteCodeEnum;
use App\Billingo\Enums\Document\LanguageEnum;
use App\Billingo\Enums\Document\TypeEnum;
use App\Billingo\Enums\EntitlementEnum;
use App\Billingo\Enums\PaymentMethodEnum;
use App\Billingo\Enums\RoundEnum;
use App\Billingo\Enums\VatEnum;
use App\Billingo\WooCommerce\Helpers\WC_Billingo_Admin_Helper;
use Symfony\Component\HttpFoundation\Response;
use App\Billingo\WooCommerce\Service\Billingo_Connection_Tester;
use App\Billingo\Service\BillingoClient;
use App\Billingo\WooCommerce\Service\Billingo_Logger;
/**
 * The Billingo plugin admin interface controller
 * This class handles the display of the admin interface and the storage of the settings
 */
class WC_Billingo_Admin_Controller
{
    // Submenu tabs constants
    const SUBTAB_API = 'api';
    const SUBTAB_INVOICE = 'invoice';
    const SUBTAB_VAT = 'vat';
    const SUBTAB_EMAIL = 'email';
    const SUBTAB_PAYMENT = 'payment';
    const SUBTAB_SUPPORT = 'support';

    const DEFAULT_PAYMENT = PaymentMethodEnum::CASH;



    /**
     * @var Billingo_Connection_Tester
     */
    private Billingo_Connection_Tester $connection_tester;

    /**
     * @var BillingoClient
     */
    private BillingoClient $client;

    /**
     * Constructor - initializes the Billingo_Connection_Tester
     */
    public function __construct()
    {
        $this->connection_tester = new Billingo_Connection_Tester();
        $this->client = new BillingoClient(get_option('wc_billingo_api_key'));
    }

    /**
     * Returns the second level submenu structure
     *
     * @return array
     */
    public function get_sub_tabs(): array
    {
        // CSRF
        wp_nonce_field('woocommerce-settings');

        $current_subsection = $this->get_current_subsection();
        
        return [
            self::SUBTAB_API => [
                'name' => __('API', 'billingo'),
                'url' => add_query_arg(['page' => 'wc-settings', 'tab' => 'settings_tab_billingo', 'subsection' => self::SUBTAB_API], admin_url('admin.php')),
                'active' => $current_subsection === self::SUBTAB_API
            ],
            self::SUBTAB_INVOICE => [
                'name' => __('Számla Beállítások', 'billingo'),
                'url' => add_query_arg(['page' => 'wc-settings', 'tab' => 'settings_tab_billingo', 'subsection' => self::SUBTAB_INVOICE], admin_url('admin.php')),
                'active' => $current_subsection === self::SUBTAB_INVOICE
            ],
            self::SUBTAB_VAT => [
                'name' => __('ÁFA', 'billingo'),
                'url' => add_query_arg(['page' => 'wc-settings', 'tab' => 'settings_tab_billingo', 'subsection' => self::SUBTAB_VAT], admin_url('admin.php')),
                'active' => $current_subsection === self::SUBTAB_VAT
            ],
            self::SUBTAB_EMAIL => [
                'name' => __('E-mail', 'billingo'),
                'url' => add_query_arg(['page' => 'wc-settings', 'tab' => 'settings_tab_billingo', 'subsection' => self::SUBTAB_EMAIL], admin_url('admin.php')),
                'active' => $current_subsection === self::SUBTAB_EMAIL
            ],
            self::SUBTAB_PAYMENT => [
                'name' => __('Fizetési mód', 'billingo'),
                'url' => add_query_arg(['page' => 'wc-settings', 'tab' => 'settings_tab_billingo', 'subsection' => self::SUBTAB_PAYMENT], admin_url('admin.php')),
                'active' => $current_subsection === self::SUBTAB_PAYMENT
            ],
            self::SUBTAB_SUPPORT => [
                'name' => __('Támogatás', 'billingo'),
                'url' => add_query_arg(['page' => 'wc-settings', 'tab' => 'settings_tab_billingo', 'subsection' => self::SUBTAB_SUPPORT], admin_url('admin.php')),
                'active' => $current_subsection === self::SUBTAB_SUPPORT
            ],
        ];
    }

   

    /**
     * Returns the current submenu
     *
     * @return string
     */
    private function get_current_subsection(): string
    {

        // CSRF check
        if (isset($_SERVER['REQUEST_METHOD']) && sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) === 'POST') {
            if (
                !isset($_POST['_wpnonce']) ||
                !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'woocommerce-settings')
            )  {
                $this->CSRF_check_failiure_alert();
                return 'error';
            }
        }
        
        return isset($_GET['subsection']) ? sanitize_text_field(wp_unslash($_GET['subsection'])) : self::SUBTAB_API;
    }

    /**
     * Show the settings page of billingo plugin
     */
    public function render_settings_page(): void
    {
        try {
            echo '<div class="wrap woocommerce">';
            echo '<form method="post" id="mainform" action="" enctype="multipart/form-data">';
            $current_subsection = $this->get_current_subsection();
            
            // show the submenu
            $submenu_html = view('Admin._sub_navigation', [
                'menu_items' => $this->get_sub_tabs()
                
            ]);
            
            echo $submenu_html;
            
            // show content based on subsection
            $this->render_content('', $current_subsection);
            
            echo '</form>';
            echo '</div>';
        } catch (\Exception $e) {
            // error message
            echo '<div class="error notice"><p>' . 
                 esc_html__('Hiba történt a beállítások megjelenítésekor: ', 'billingo') . esc_html($e->getMessage()) . 
                 '</p></div>';
            
        }
    }

    /**
     * Displays the content of the selected section
     *
     * @param string $section
     * @param string $subsection
     */
    private function render_content(string $section, string $subsection): void
    {
        try {
            // show content based on subsection
            switch ($subsection) {
                case self::SUBTAB_API:
                    $this->render_api_settings();
                    break;
                
                case self::SUBTAB_INVOICE:
                    $this->render_invoice_settings();
                    break;
                
                case self::SUBTAB_VAT:
                    $this->render_vat_settings();
                    break;
                
                case self::SUBTAB_EMAIL:
                    $this->render_email_settings();
                    break;
                
                case self::SUBTAB_PAYMENT:
                    $this->render_payment_settings();
                    break;
                
                case self::SUBTAB_SUPPORT:
                    $this->render_support();
                    break;
                
                default:
                    // default content, if no specific view
                    $this->render_api_settings();
            }
        } catch (\Exception $e) {
            // error message
            echo '<div class="error notice"><p>' . 
                 esc_html__('Hiba történt: ', 'billingo') . esc_html($e->getMessage()) . 
                 '</p></div>';
        }
    }

    /**
     * Displays the API settings
     */
    private function render_api_settings(): void
    {
        
        $error_message = '';
        $document_blocks =  self::get_formatted_document_blocks($this->client);
       
      

        $status = $this->connection_tester->testConnection();
        $message = '';
        switch ($status) {
            case 'not_configured':
                $message = 'Nincs API kapcsolat beállítva';
                break;
                
            case 'error':
                $message = 'Az API kapcsolat hiba miatt nem jött létre';
                break;
                
            case 'success':
                $message = 'Az API kapcsolat sikeresen létrejött';
                break;
        }
            
        
        // CSRF 
        wp_nonce_field('woocommerce-settings');
        
        // API settings show
        $html = view('Admin.subtabs.api_settings', [
            'api_key' => get_option('wc_billingo_api_key', ''),
            'error_message' => $error_message,
            'document_blocks' => $document_blocks,
            'selected_block' => get_option('wc_billingo_invoice_block', ''),
            'billingo_api_connection_status' => $status,
            'billingo_api_connection_message' => $message,
        ]);
        
        echo $html;
    }


    /**
     * Displays the invoice settings
     */
    private function render_invoice_settings(): void
    {

        $data_eraser_code = get_wp_translated_enum(DeleteCodeEnum::class, true);
        $bank_accounts = $this->get_formatted_bank_accounts();
        
        // Get available payment methods
        $bpms = get_wp_translated_enum(PaymentMethodEnum::class, true);
        
        // Get available languages
        $langs = get_wp_translated_enum(LanguageEnum::class, true);
        
        // Get rounding options
        $roundings = get_wp_translated_enum(RoundEnum::class, true);
        
        // Get order statuses
        $order_statuses = wc_get_order_statuses();
        
        // CSRF
        wp_nonce_field('woocommerce-settings');
        
        $html = view('Admin.subtabs.invoice_settings', [
            'bank_accounts' => $bank_accounts,
            'bank_account_hu' => get_option('wc_billingo_bank_account_huf', ''),
            'bank_account_eu' => get_option('wc_billingo_bank_account_eur', ''),
            'default_payment' => get_option('wc_billingo_fallback_payment', self::DEFAULT_PAYMENT->value),
            'invoice_lang' => get_option('wc_billingo_invoice_lang', 'hu'),
            'note_value' => get_option('wc_billingo_note', ''),
            'invoice_round' => get_option('wc_billingo_invoice_round', ''),
            'unit' => get_option('wc_billingo_unit', ''),
            'manual_type' => get_option('wc_billingo_manual_type', 'invoice'),
            'erase_code' => get_option('wc_billingo_is_generate_erase_code', ''),
            'auto_state' => get_option('wc_billingo_auto_state', ''),
            'company_name' => get_option('wc_billingo_company_name', '0'),
            'vat_number_form_custom' => get_option('wc_billingo_vat_number_form_custom', ''),
            'vat_number_notice' => get_option('wc_billingo_vat_number_notice', 'Az adószám megadása kötelező magyar adóalanyok esetében, ezért amennyiben rendelkezik adószámmal, azt kötelező megadni a számlázási adatoknál.'),

            // Checkbox options
            'electronic' => get_option('wc_billingo_electronic', '0'),
            'product_sync' => get_option('wc_billingo_product_sync', '0'),
            'disable_proforma' => get_option('wc_billingo_disable_proforma_invoicing', '0'),
            'note_barion' => get_option('wc_billingo_note_barion', '0'),
            'sku' => get_option('wc_billingo_sku', '0'),
            'mark_paid_without_fulfillment' => get_option('mark_paid_without_financial_fulfillment', '0'),
            'flip_name' => get_option('wc_billingo_flip_name', '0'),
            'invoice_lang_wpml' => get_option('wc_billingo_invoice_lang_wpml', '0'),
            'note_orderid' => get_option('wc_billingo_note_orderid', '0'),
            'block_child_orders' => get_option('wc_billingo_block_child_orders', '0'),
            'vat_number_form' => get_option('wc_billingo_vat_number_form', '0'),
            'vat_number_form_checkbox_custom' => get_option('wc_billingo_vat_number_form_checkbox_custom', '0'),

            // Select options
            'auto_storno' => get_option('wc_billingo_auto_storno', 'no'),
            'payment_request_auto' => get_option('wc_billingo_payment_request_auto', 'no'),
            'auto' => get_option('wc_billingo_auto', 'no'),
            
            // Available options
            'bpms' => $bpms,
            'langs' => $langs,
            'roundings' => $roundings,
            'order_statuses' => $order_statuses
        ]);
        
        echo $html;
    }

    /**
     * Vat Settings Display
     */
    private function render_vat_settings(): void
    {
        $taxes = getEnumValues(VatEnum::class);
        $onlyTaxes = [];
        
        array_walk($taxes, function (&$tax) use (&$onlyTaxes) {
            if (strpos($tax, '%')) {
                $onlyTaxes[$tax] = $tax;
            }
        });
        
        $firstEntitlement = ['' => ''];
        $entitlements = $this->get_indexed_array_from_enum(EntitlementEnum::class);
        $entitlements = $firstEntitlement + $entitlements;
        
        // Get tax_override_choice
        $tax_override_choice = (int)get_option('wc_billingo_tax_override_choice', 1);
        
        // CSRF
        wp_nonce_field('woocommerce-settings');
        
        $html = view('Admin.subtabs.tax_settings', [
            'tax_override' => (int)get_option('wc_billingo_tax_override', 0),
            'tax_override_choice' => $tax_override_choice,
            'selected_tax_override_entitlement' => get_option('wc_billingo_tax_override_entitlements', ''),
            'selected_tax_override_value' => get_option('wc_billingo_tax_override_value', ''),
            'selected_tax_override_zero_entitlement' => get_option('wc_billingo_tax_override_zero_entitlements', ''),
            'tax_override_include_carrier' => (int)get_option('wc_billingo_tax_override_include_carrier', 0),
            'tax_shipping_pirce_type_is_net' => (int)get_option('wc_billingo_tax_shipping_pirce_type_is_net', 0),
            'always_add_carrier' => (int)get_option('wc_billingo_always_add_carrier', 0),
            'entitlements' => $entitlements,
            'taxes' => $onlyTaxes
        ]);
        
        echo $html;
    }

    /**
     * Email settings show
     */
    private function render_email_settings(): void
    {
        $options = [
            'no' => __('Nem', 'billingo'),
            'billingo' => __('Küldés Billingon keresztül', 'billingo'),
            'attach' => __('Csatolás a WooCommerce E-mailhez', 'billingo'),
            'both' => __('Mindkét előző opció', 'billingo'),
        ];
        
        // CSRF
        wp_nonce_field('woocommerce-settings');

        $html = view('Admin.subtabs.email_settings', [
            'options' => $options,
            'email_invoice' => get_option('wc_billingo_email', 'no'),
            'email_proforma' => get_option('wc_billingo_proforma_email', 'no'),
            'email_storno' => get_option('wc_billingo_storno_email', 'no'),
            'btn_text_proforma' => get_option('wc_billingo_proforma_email_woo_btn', __('Díjbekérő letöltése', 'billingo')),
            'btn_text_invoice' => get_option('wc_billingo_email_woo_btn', __('Számla letöltése', 'billingo')),
            'btn_text_storno' => get_option('wc_billingo_storno_email_woo_btn', __('Storno számla letöltése', 'billingo')),
            'text_proforma' => get_option('wc_billingo_proforma_email_woo_text', __('Díjbekérője elkészült, melyet az alábbi linken tud letölteni.', 'billingo')),
            'text_invoice' => get_option('wc_billingo_email_woo_text', __('Számlája elkészült, melyet az alábbi linken tud letölteni.', 'billingo')),
            'text_storno' => get_option('wc_billingo_storno_email_woo_text', __('Storno számlája elkészült, melyet az alábbi linken tud letölteni.', 'billingo'))
        ]);
        
        echo $html;
    }

    /**
     * Payment settings show
     */
    private function render_payment_settings(): void
    {
        

        $payment_methods = [];
        $billingo_payment_methods = get_wp_translated_enum(PaymentMethodEnum::class);

            foreach (WC_Billingo_Admin_Helper::get_available_payment_methods() as $key => $name) {
                $payment_methods[] = [
                    'key' => $key,
                    'name' => $name,
                    'bpm' => get_option('wc_billingo_payment_method_' . $key),
                    'due' => (int)get_option('wc_billingo_paymentdue_' . $key, 0),
                    'pay' => (int)get_option('wc_billingo_mark_as_paid_' . $key, 0),
                    'pay2' => (int)get_option('wc_billingo_mark_as_paid2_' . $key, 0),
                    'pro' => (int)get_option('wc_billingo_proforma_' . $key, 0),
                ];
            }  
        
        
        
        // CSRF
        wp_nonce_field('woocommerce-settings');

        $html = view('Admin.subtabs.payment_settings', [
            'payment_methods' => $payment_methods,
            'billingo_payment_methods' => $billingo_payment_methods
        ]);
        
        echo $html;
    }

    /**
     * Support page show
     */
    private function render_support(): void
    {
        global $wp_version;
        if(isset($_SERVER['SERVER_SOFTWARE'])) {
            $server_info = sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE']));
        } else {
            $server_info = 'Unknown';
        }
        // Debugging infos
        $debug_info = [
            'Plugin Version' => defined('BILLINGO_VERSION') ? BILLINGO_VERSION : 'Unknown',
            'WordPress Version' => $wp_version,
            'PHP Version' => phpversion(),
            'WooCommerce Version' => defined('WC_VERSION') ? WC_VERSION : 'Not active',
            'Server Info' => $server_info,
            'Memory Limit' => ini_get('memory_limit'),
        ];
        
        $debug_code = json_encode($debug_info, JSON_PRETTY_PRINT);
        
        // logs
        $log_path = BILLINGO__PLUGIN_DIR . 'log/';

        $combined_logs = '';

        if (file_exists($log_path) && is_dir($log_path)) {
            $files = array_filter(scandir($log_path), function ($file) use ($log_path) {
                // filter the upper going directories
                return $file !== '.' && $file !== '..' && is_file($log_path . $file);
            });

            // return the last 2 log files content
            $latest_files = array_slice($files, -2);

            foreach ($latest_files as $file) {
                $combined_logs .= "=== $file ===\n";
                $combined_logs .= file_get_contents($log_path . $file);
                $combined_logs .= "\n\n";
            }
        }

        $html = view('Admin.subtabs.support', [
            'debug_code' => $debug_code,
            'log_files' => $combined_logs,
        ]);
        
        echo $html;
    }

    /**
     * @param string $enum
     * @return array
     */
    private function get_indexed_array_from_enum(string $enum): array
    {
        $indexedArray = [];
        foreach ($enum::cases() as $enum) {
            $indexedArray[$enum->value] = $enum->value;
        }

        return $indexedArray;
    }

    /**
     * @return array
     */
    private function get_formatted_bank_accounts(): array
    {
        $bank_accounts = [];

        $response = $this->client->bankAccount()
            ->getAll()
            ->getResponse();

        if ($response->getStatusCode() === Response::HTTP_OK) {
            $response
                ->getData()
                ->each(function ($bank_account) use (&$bank_accounts) {
                    $bank_accounts[$bank_account->id] = $bank_account->name . " ({$bank_account->currency})";
                });
        }

        $bank_accounts[''] = __('Alapértelmezett', 'billingo');

        return $bank_accounts;
    }

    /**
     * @return array
     */
    private function get_formatted_document_blocks(): array
    {
        $document_blocks = [];

        $response = $this->client->documentBlock()
            ->query()
            ->whereType(TypeEnum::INVOICE->value)
            ->getApi()
            ->getResponse();

        if ($response->getStatusCode() === Response::HTTP_OK) {
            $response
                ->getData()
                ->each(function ($document_block) use (&$document_blocks) {
                    $document_blocks[$document_block->id] = $document_block->name . " ({$document_block->prefix})";
                });
        }
        
        $document_blocks[''] = __('Alapértelmezett', 'billingo');

        return $document_blocks;
    }

    /**
     * Save settings
     * 
     * @param string|null $subsection The current subsection (if null, it will be read from the URL)
     */
    public function process_settings_save(?string $subsection = null): void
    {
        
        // CSRF check
        if (
            !isset($_POST['_wpnonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'woocommerce-settings')
            )  {
            $this->CSRF_check_failiure_alert();
            return;
        }
        // Only process if POST request is received
        if(isset($_SERVER['REQUEST_METHOD'])) {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST)) {
                return;
            }
        } else {
            return;
        }
        
        // get subsection from hidden field
        if (isset($_POST['subsection']) && !empty($_POST['subsection'])) {
            $subsection = sanitize_text_field(wp_unslash($_POST['subsection']));
        }
        // if no subsection is set, get it from the URL
        elseif (is_null($subsection)) {
            $subsection = $this->get_current_subsection();
        }
        
        // save settings based on the current subsection
        switch ($subsection) {
            case self::SUBTAB_API:
            case 'api':
                $this->save_api_settings();
                break;
                
            case self::SUBTAB_INVOICE:
            case 'invoice':
                $this->save_invoice_settings();
                break;
                
            case self::SUBTAB_VAT:
            case 'vat':
                $this->save_vat_settings();
                break;
                
            case self::SUBTAB_EMAIL:
            case 'email':
                $this->save_email_settings();
                break;
                
            case self::SUBTAB_PAYMENT:
            case 'payment':
                $this->save_payment_settings();
                break;
        }
        
    }


    private function CSRF_check_failiure_alert(): void
    {
        add_action('admin_notices', function() {
            echo '<div class="error notice"><p>' . esc_html__('Biztonsági ellenőrzés sikertelen. Kérjük próbálja újra.', 'billingo') . '</p></div>';
        });
    }
    
    /**
     * API settings save
     */
    private function save_api_settings(): void
    {

             // CSRF check
             if (
                !isset($_POST['_wpnonce']) ||
                !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'woocommerce-settings')
            )  {
                $this->CSRF_check_failiure_alert();
                return;
            }

        if (isset($_POST['wc_billingo_api_key'])) {
            $valueApiKey = sanitize_text_field(wp_unslash($_POST['wc_billingo_api_key']));
            update_option('wc_billingo_api_key', $valueApiKey);
        }
        
        if (isset($_POST['wc_billingo_invoice_block'])) {
            $valueInvoiceBlock = sanitize_text_field(wp_unslash($_POST['wc_billingo_invoice_block']));
            update_option('wc_billingo_invoice_block', $valueInvoiceBlock);
        }
    }
    
    /**
     * Invoice settings save
     */
    private function save_invoice_settings(): void
    {

             // CSRF check
             if (
                !isset($_POST['_wpnonce']) ||
                !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'woocommerce-settings')
            )  {
                $this->CSRF_check_failiure_alert();
                return;
            }
     
        // text fields
        $text_fields = [
            'wc_billingo_bank_account_huf',
            'wc_billingo_bank_account_eur',
            'wc_billingo_note',
            'wc_billingo_unit',
            'wc_billingo_fallback_payment',
            'wc_billingo_invoice_lang',
            'wc_billingo_manual_type',
            'wc_billingo_is_generate_erase_code',
            'wc_billingo_auto_state',
            'wc_billingo_company_name',
            'wc_billingo_invoice_round',
            'wc_billingo_auto',
            'wc_billingo_payment_request_auto',
            'wc_billingo_auto_storno',
            'wc_billingo_vat_number_form_custom',
            'wc_billingo_vat_number_notice'
        ];
        
        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field(wp_unslash($_POST[$field]));
                update_option($field, $value);
            }
        }
        
        // checkbox fields
        $checkbox_fields = [
            'wc_billingo_disable_proforma_invoicing',
            'wc_billingo_note_orderid',
            'wc_billingo_note_barion',
            'wc_billingo_electronic',
            'wc_billingo_product_sync',
            'wc_billingo_sku',
            'mark_paid_without_financial_fulfillment',
            'wc_billingo_flip_name',
            'wc_billingo_invoice_lang_wpml',
            'wc_billingo_block_child_orders',
            'wc_billingo_vat_number_form',
            'wc_billingo_vat_number_form_checkbox_custom',
        ];
        
        foreach ($checkbox_fields as $field) {
            if (isset($_POST[$field])) {
                update_option($field, 1);
            } else {
                update_option($field, 0);
            }
        }
    }
    
    /**
     * Vat settings save
     */
    private function save_vat_settings(): void
    {

        // CSRF check     // CSRF check
        if (
            !isset($_POST['_wpnonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'woocommerce-settings')
        )  {
            $this->CSRF_check_failiure_alert();
            return;
        }

        if (isset($_POST['wc_billingo_tax_override'])) {
            update_option('wc_billingo_tax_override', (int)$_POST['wc_billingo_tax_override']);
        }
        
        if (isset($_POST['wc_billingo_tax_override_choice'])) {
            $choice_value = (int)$_POST['wc_billingo_tax_override_choice'];
            update_option('wc_billingo_tax_override_choice', $choice_value);
        }
        
        $text_fields = [
            'wc_billingo_tax_override_entitlements',
            'wc_billingo_tax_override_value',
            'wc_billingo_tax_override_zero_entitlements'
        ];
        
        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field(wp_unslash($_POST[$field]));
                update_option($field, $value);
            }
        }
        
        $checkbox_fields = [
            'wc_billingo_tax_override_include_carrier',
            'wc_billingo_always_add_carrier',
            'wc_billingo_tax_shipping_pirce_type_is_net'
        ];
        
        foreach ($checkbox_fields as $field) {
            if (isset($_POST[$field])) {
                update_option($field, 1);
            } else {
                update_option($field, 0);
            }
        }
    }
    
    /**
     * Email settings save
     */
    private function save_email_settings(): void
    {

            // CSRF check
            if (
                !isset($_POST['_wpnonce']) ||
                !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'woocommerce-settings')
            )  {
                $this->CSRF_check_failiure_alert();
                return;
            }

        if (isset($_POST['billingo_email_settings']) && is_array($_POST['billingo_email_settings'])) {
            //maradhat Lead azt mondta
            $email_settings = wp_unslash($_POST['billingo_email_settings']);
            foreach ($email_settings as $key => $value) {
                update_option(sanitize_text_field($key), sanitize_text_field($value));
            }
        }
    }
    
    /**
     * Payment settings save
     */
    private function save_payment_settings(): void
    {

        // CSRF check
        if (
            !isset($_POST['_wpnonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'woocommerce-settings')
        )  {
            $this->CSRF_check_failiure_alert();
            return;
        }


        if (isset($_POST['billingo_payment_settings']) && is_array($_POST['billingo_payment_settings'])) {
            //maradhat Lead azt mondta
            $billingo_payment_settings = wp_unslash($_POST['billingo_payment_settings']);
            foreach ($billingo_payment_settings as $id => $fields) {
                $id = sanitize_text_field($id);
                update_option('wc_billingo_payment_method_' . $id, sanitize_text_field($fields['billingo_payment_method']));
                update_option('wc_billingo_paymentdue_' . $id, (int)$fields['paymentdue']);
                update_option('wc_billingo_mark_as_paid_' . $id, (int)($fields['mark_as_paid'] ?? 0));
                update_option('wc_billingo_mark_as_paid2_' . $id, (int)($fields['mark_as_paid2'] ?? 0));
                update_option('wc_billingo_proforma_' . $id, (int)($fields['proforma'] ?? 0));
            }
        }
    }
} 