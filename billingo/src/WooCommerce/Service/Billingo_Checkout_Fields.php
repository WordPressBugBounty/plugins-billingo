<?php

namespace App\Billingo\WooCommerce\Service;
use App\Billingo\WooCommerce\Service\Billingo_Logger;

class Billingo_Checkout_Fields
{
    public static function init(): void
    {
        // Kezdő logolás
        if (class_exists('App\Billingo\WooCommerce\Service\Billingo_Logger')) {
           Billingo_Logger::info('Billingo_Checkout_Fields::init() called.');
        } else {
            error_log('Billingo_Checkout_Fields::init() called.');
        }

        // HuCommerce plugin ellenőrzése
        $is_hucommerce_active = self::is_hucommerce_active();
        if (class_exists('App\Billingo\WooCommerce\Service\Billingo_Logger')) {
            Billingo_Logger::info('Is HuCommerce active? ' . ($is_hucommerce_active ? 'Yes' : 'No'));
        } else {
            error_log('Is HuCommerce active? ' . ($is_hucommerce_active ? 'Yes' : 'No'));
        }

        if ($is_hucommerce_active) {
            if (class_exists('App\Billingo\WooCommerce\Service\Billingo_Logger')) {
                Billingo_Logger::info('HuCommerce is active, returning.');
            } else {
                error_log('HuCommerce is active, returning.');
            }
            return; // Ha HuCommerce aktív, ne adjuk hozzá a mezőt
        }

        // Csak akkor adjuk hozzá a mezőt, ha engedélyezve van
        $vat_form_option = get_option('wc_billingo_vat_number_form');
        $is_vat_form_enabled = wcFlexibleIsTrue($vat_form_option);

        if (class_exists('App\Billingo\WooCommerce\Service\Billingo_Logger')) {
            Billingo_Logger::info('wc_billingo_vat_number_form option value: ' . var_export($vat_form_option, true));
            Billingo_Logger::info('Is VAT form enabled (wcFlexibleIsTrue)? ' . ($is_vat_form_enabled ? 'Yes' : 'No'));
        } else {
            error_log('wc_billingo_vat_number_form option value: ' . var_export($vat_form_option, true));
            error_log('Is VAT form enabled (wcFlexibleIsTrue)? ' . ($is_vat_form_enabled ? 'Yes' : 'No'));
        }

        if ($is_vat_form_enabled) {
            if (class_exists('App\Billingo\WooCommerce\Service\Billingo_Logger')) {
                Billingo_Logger::info('Adding VAT number field hooks.');
            } else {
                error_log('Adding VAT number field hooks.');
            }
            add_filter('woocommerce_checkout_fields', [self::class, 'add_vat_number_field']);
            add_action('woocommerce_checkout_process', [self::class, 'validate_vat_number_field']);
            add_action('woocommerce_checkout_update_order_meta', [self::class, 'save_vat_number_field']);
            add_action('woocommerce_admin_order_data_after_billing_address', [self::class, 'display_vat_number_in_admin']);
            add_action('woocommerce_order_details_after_customer_details', [self::class, 'display_vat_number_in_order_details']);
        } else {
            if (class_exists('App\Billingo\WooCommerce\Service\Billingo_Logger')) {
                Billingo_Logger::info('VAT number field hooks NOT added because the option is disabled.');
            } else {
                error_log('VAT number field hooks NOT added because the option is disabled.');
            }
        }

        // Webhook feliratkozás a rendelés leadására - termék teljes árak mentéséhez
        add_action('woocommerce_checkout_order_processed', [self::class, 'save_product_regular_prices'], 10, 1);
        add_action('woocommerce_thankyou', [self::class, 'save_product_regular_prices'], 10, 1);
        
        if (class_exists('App\Billingo\WooCommerce\Service\Billingo_Logger')) {
            Billingo_Logger::info('Product regular price hooks added.');
        } else {
            error_log('Product regular price hooks added.');
        }
    }

    /**
     * Ellenőrzi, hogy a HuCommerce plugin aktív-e
     */
    private static function is_hucommerce_active(): bool
    {
        // Ensure plugin.php is loaded for is_plugin_active function
        if (!function_exists('is_plugin_active')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        return class_exists('HuCommerce') || function_exists('hucommerce_init') || is_plugin_active('hucommerce/hucommerce.php');
    }

    /**
     * Hozzáadja az adószám mezőt a checkout form-hoz
     */
    public static function add_vat_number_field(array $fields): array
    {
        $notice = get_option('wc_billingo_vat_number_notice', 'Az adószám megadása kötelező magyar adóalanyok esetében, ezért amennyiben rendelkezik adószámmal, azt kötelező megadni a számlázási adatoknál.');
        
        $fields['billing']['billing_vat_number'] = [
            'label' => __('Adószám', 'billingo'),
            'placeholder' => __('Adószám (opcionális)', 'billingo'),
            'required' => false,
            'class' => ['form-row-wide'],
            'priority' => 120,
            'description' => !empty($notice) ? $notice : '',
            'type' => 'text'
        ];

        return $fields;
    }

    /**
     * Validálja az adószám mezőt (opcionális validáció)
     */
    public static function validate_vat_number_field(): void
    {
        // Itt lehetne validálni az adószám formátumát, ha szükséges
        // Jelenleg csak alapvető ellenőrzést végzünk
        if (isset($_POST['billing_vat_number']) && !empty($_POST['billing_vat_number'])) {
            $vat_number = sanitize_text_field($_POST['billing_vat_number']);
            
            // Magyar adószám formátum ellenőrzése (opcionális)
            if (!empty($vat_number) && !self::is_valid_hungarian_vat_number($vat_number)) {
                // Nem blokkoljuk a rendelést, csak figyelmeztetést adunk
                // wc_add_notice(__('Az adószám formátuma nem megfelelő.', 'billingo'), 'notice');
            }
        }
    }

    /**
     * Menti az adószámot a rendelés meta adataiba
     */
    public static function save_vat_number_field(int $order_id): void
    {
        if (isset($_POST['billing_vat_number']) && !empty($_POST['billing_vat_number'])) {
            $vat_number = sanitize_text_field($_POST['billing_vat_number']);
            update_post_meta($order_id, 'adoszam', $vat_number);
            update_post_meta($order_id, '_billing_vat_number', $vat_number);
        }
    }

    /**
     * Megjeleníti az adószámot az admin rendelés oldalon
     */
    public static function display_vat_number_in_admin($order): void
    {
        $vat_number = self::get_vat_number_from_order($order);
        
        if (!empty($vat_number)) {
            echo '<p><strong>' . __('Adószám:', 'billingo') . '</strong> ' . esc_html($vat_number) . '</p>';
        }
    }

    /**
     * Megjeleníti az adószámot a rendelés részleteiben (frontend)
     */
    public static function display_vat_number_in_order_details($order): void
    {
        $vat_number = self::get_vat_number_from_order($order);
        
        if (!empty($vat_number)) {
            echo '<tr><th>' . __('Adószám:', 'billingo') . '</th><td>' . esc_html($vat_number) . '</td></tr>';
        }
    }

    /**
     * Lekéri az adószámot a rendelésből (több forrásból)
     */
    public static function get_vat_number_from_order($order): string
    {
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }

        if (!$order) {
            return '';
        }

        $order_id = $order->get_id();

        // Először az "adoszam" meta mezőből próbáljuk
        $vat_number = get_post_meta($order_id, 'adoszam', true);
        
        // Ha nincs, akkor a _billing_vat_number-ből
        if (empty($vat_number)) {
            $vat_number = get_post_meta($order_id, '_billing_vat_number', true);
        }

        // Ha van custom meta mező beállítva
        $custom_field = get_option('wc_billingo_vat_number_form_custom', '');
        if (!empty($custom_field) && empty($vat_number)) {
            $vat_number = get_post_meta($order_id, $custom_field, true);
        }

        // HuCommerce kompatibilitás
        if (empty($vat_number) && self::is_hucommerce_active()) {
            $vat_number = get_post_meta($order_id, '_billing_tax_number', true);
        }

        return !empty($vat_number) ? sanitize_text_field($vat_number) : '';
    }

    /**
     * Ellenőrzi a magyar adószám formátumát (alapvető ellenőrzés)
     */
    private static function is_valid_hungarian_vat_number(string $vat_number): bool
    {
        // Eltávolítjuk a szóközöket és kötőjeleket
        $vat_number = preg_replace('/[\s\-]/', '', $vat_number);
        
        // Magyar adószám: 8 számjegy-1-számjegy
        return preg_match('/^\d{8}-?\d{1}-?\d{2}$/', $vat_number) === 1;
    }

    /**
     * Mentés a termék regisztrális árának a rendelés meta adataiba
     */
    public static function save_product_regular_prices($order_id): void
    {
        if (class_exists('App\Billingo\WooCommerce\Service\Billingo_Logger')) {
            Billingo_Logger::info('save_product_regular_prices called for order ID: ' . $order_id);
        } else {
            error_log('save_product_regular_prices called for order ID: ' . $order_id);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            if (class_exists('App\Billingo\WooCommerce\Service\Billingo_Logger')) {
                Billingo_Logger::error('Order not found for ID: ' . $order_id);
            } else {
                error_log('Order not found for ID: ' . $order_id);
            }
            return;
        }

        $items = $order->get_items();
        foreach ($items as $item_id => $item) {
            $product = $item->get_product();
            if ($product && method_exists($product, 'get_regular_price')) {
                $regular_price = $product->get_regular_price();
                
                if (!empty($regular_price)) {
                    // Az order item meta-ba mentjük, nem a termék meta-ba
                    wc_add_order_item_meta($item_id, 'wc_billingo_product_full_price_without_sale', $regular_price);
                    
                    if (class_exists('App\Billingo\WooCommerce\Service\Billingo_Logger')) {
                        Billingo_Logger::info("Regular price saved for item ID: {$item_id}, Product ID: {$product->get_id()}, Regular Price: {$regular_price}");
                    } else {
                        error_log("Regular price saved for item ID: {$item_id}, Product ID: {$product->get_id()}, Regular Price: {$regular_price}");
                    }
                } else {
                    if (class_exists('App\Billingo\WooCommerce\Service\Billingo_Logger')) {
                        Billingo_Logger::warning("Empty regular price for Product ID: {$product->get_id()}");
                    } else {
                        error_log("Empty regular price for Product ID: {$product->get_id()}");
                    }
                }
            } else {
                if (class_exists('App\Billingo\WooCommerce\Service\Billingo_Logger')) {
                    Billingo_Logger::error("Product not found or get_regular_price method missing for item ID: {$item_id}");
                } else {
                    error_log("Product not found or get_regular_price method missing for item ID: {$item_id}");
                }
            }
        }
        
        if (class_exists('App\Billingo\WooCommerce\Service\Billingo_Logger')) {
            Billingo_Logger::info('save_product_regular_prices completed for order ID: ' . $order_id);
        } else {
            error_log('save_product_regular_prices completed for order ID: ' . $order_id);
        }
    }
} 