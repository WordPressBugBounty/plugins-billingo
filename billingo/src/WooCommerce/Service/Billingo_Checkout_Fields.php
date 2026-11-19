<?php

namespace App\Billingo\WooCommerce\Service;
use App\Billingo\WooCommerce\Service\Billingo_Logger;
use App\Billingo\Service\BillingoClient;
use App\Billingo\Models\Util\TaxNumber;
use App\Billingo\Enums\CheckTaxNumberMessageEnum;

class Billingo_Checkout_Fields
{
    public const AJAX_ACTION = 'wc_billingo_lookup_vat';
    public const NONCE_ACTION = 'wc_billingo_vat_lookup';

    /** Egy IP-ről ennyi lekérdezés engedett LOOKUP_WINDOW másodpercen belül. */
    private const LOOKUP_LIMIT = 10;
    private const LOOKUP_WINDOW = 600;

    private static bool $vat_fields_initialized = false;
    private static bool $price_hooks_initialized = false;

    /**
     * Inicializálja a termék ár mentés hook-okat (mindig szükséges)
     */
    public static function init_product_price_hooks(): void
    {
        if (self::$price_hooks_initialized) {
            return; // Csendben kihagyjuk, ha már inicializálva van
        }
        self::$price_hooks_initialized = true;

        // Termék árak mentése hook-ok
        add_action('woocommerce_thankyou', [self::class, 'save_product_original_prices'], 5, 1);

    }

    /**
     * Inicializálja a VAT mező hook-okat (csak checkout/thankyou oldalon)
     */
    public static function init_vat_fields(): void
    {
        if (self::$vat_fields_initialized) {
            return;
        }
        self::$vat_fields_initialized = true;

        if (class_exists('App\Billingo\WooCommerce\Service\Billingo_Logger')) {
            Billingo_Logger::info('VAT fields initialization started');
        }

        // HuCommerce plugin ellenőrzése
        $is_hucommerce_active = self::is_hucommerce_active();
        if ($is_hucommerce_active) {
            if (class_exists('App\Billingo\WooCommerce\Service\Billingo_Logger')) {
                Billingo_Logger::info('HuCommerce is active, skipping VAT fields');
            }
            return;
        }

        // Csak akkor adjuk hozzá a mezőt, ha engedélyezve van
        $vat_form_option = get_option('wc_billingo_vat_number_form');
        $is_vat_form_enabled = wcFlexibleIsTrue($vat_form_option);

        if ($is_vat_form_enabled) {
            add_filter('woocommerce_checkout_fields', [self::class, 'add_vat_number_field']);
            add_action('woocommerce_checkout_process', [self::class, 'validate_vat_number_field']);
            // A rendelés objektumára menti az adatot (HPOS-kompatibilis, külön update_post_meta nélkül).
            add_action('woocommerce_checkout_create_order', [self::class, 'save_vat_number_to_order']);
            add_action('woocommerce_admin_order_data_after_billing_address', [self::class, 'display_vat_number_in_admin']);
            add_action('woocommerce_order_details_after_customer_details', [self::class, 'display_vat_number_in_order_details']);

            // Élő adószám-lekérdezés / cégadat-kitöltés a pénztár oldalon
            add_action('wp_enqueue_scripts', [self::class, 'enqueue_checkout_script']);
            add_action('wp_ajax_' . self::AJAX_ACTION, [self::class, 'ajax_lookup_vat_number']);
            // A vásárlók többsége vendég: a végpont szándékosan elérhető kijelentkezett látogatónak is,
            // ezért nonce-szal, korlátozott gyakorisággal és szűkített válasszal (csak cégnév és cím) működik.
            add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [self::class, 'ajax_lookup_vat_number']);

            if (class_exists('App\Billingo\WooCommerce\Service\Billingo_Logger')) {
                Billingo_Logger::info('VAT number field hooks added');
            }
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

        // HOTFIX: VALÓS HUCOMMERCE DETEKTÁLÁS
        return is_plugin_active('surbma-magyar-woocommerce/surbma-magyar-woocommerce.php');
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
     * Validálja az adószám mezőt.
     *
     * Ha az élő NAV-ellenőrzés be van kapcsolva (wc_billingo_vat_number_live_check), a
     * megadott adószámot ténylegesen leellenőrzi a Billingo API-n (NAV) keresztül, és
     * hibás/nem létező adószám esetén blokkolja a rendelés leadását. API-hiba vagy hálózati
     * probléma esetén "fail-open"-nel működik (nem blokkol), hogy egy átmeneti Billingo/NAV
     * kiesés ne akassza meg a boltban a vásárlást.
     */
    public static function validate_vat_number_field(): void
    {
        if (!isset($_POST['billing_vat_number']) || empty($_POST['billing_vat_number'])) {
            return;
        }

        $vat_number = sanitize_text_field(wp_unslash($_POST['billing_vat_number']));

        if (!self::is_valid_hungarian_vat_number($vat_number)) {
            // Nem blokkoljuk a rendelést csak a formai hiba miatt, csak figyelmeztetünk.
            wc_add_notice(__('Az adószám formátuma nem megfelelő.', 'billingo'), 'notice');
            return;
        }

        if (!wcFlexibleIsTrue(get_option('wc_billingo_vat_number_live_check'))) {
            return;
        }

        $normalized = self::normalize_hungarian_vat_number($vat_number);

        if ($normalized === null) {
            wc_add_notice(__('Az adószám formátuma nem megfelelő.', 'billingo'), 'error');
            return;
        }

        try {
            $response = (new BillingoClient(get_option('wc_billingo_api_key', '')))
                ->util()
                ->checkTaxNumber($normalized)
                ->getResponse();

            if ($response->getStatusCode() !== 200) {
                Billingo_Logger::warning('Adószám NAV-ellenőrzés sikertelen (HTTP ' . $response->getStatusCode() . '), a checkout nem lett blokkolva.');
                return;
            }

            $data = $response->getData();
            $result = $data instanceof TaxNumber ? $data->result : null;

            if (in_array($result, [
                CheckTaxNumberMessageEnum::INVALID_TAX_NUMBER->value,
                CheckTaxNumberMessageEnum::NON_EXIST_TAX_NUMBER->value,
            ], true)) {
                wc_add_notice(__('A megadott adószám a NAV nyilvántartása szerint nem érvényes. Kérjük, ellenőrizze az adószámot.', 'billingo'), 'error');
            }
            // EXTERNAL_NAV_SERVICE_UNREACHABLE / NO_ONLINE_SZAMLA_SETTINGS esetén fail-open: nem blokkoljuk a vásárlást.
        } catch (\Throwable $e) {
            // \Throwable: egy hibás API-válasz TypeError-t (\Error) is okozhat, ami fail-open helyett fatal lenne.
            Billingo_Logger::error('Adószám NAV-ellenőrzés kivétel: ' . $e->getMessage() . ' — a checkout nem lett blokkolva.');
        }
    }

    /**
     * A pénztár oldali adószám-lekérdezés JS-e és adatai (csak ha az élő ellenőrzés vagy a kitöltés be van kapcsolva).
     */
    public static function enqueue_checkout_script(): void
    {
        if (!function_exists('is_checkout') || !is_checkout() || (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received'))) {
            return;
        }

        $liveCheck = wcFlexibleIsTrue(get_option('wc_billingo_vat_number_live_check'));
        $autofill = wcFlexibleIsTrue(get_option('wc_billingo_vat_number_autofill'));

        if (!$liveCheck && !$autofill) {
            return;
        }

        $path = dirname(__DIR__) . '/assets/js/checkout-vat.js';

        wp_enqueue_script(
            'billingo_checkout_vat',
            plugins_url('/../assets/js/checkout-vat.js', __FILE__),
            ['jquery'],
            is_file($path) ? (string)filemtime($path) : null,
            true
        );

        wp_localize_script('billingo_checkout_vat', 'wc_billingo_vat_lookup', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'action' => self::AJAX_ACTION,
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'autofill' => $autofill,
            'checking' => __('Adószám ellenőrzése...', 'billingo'),
        ]);
    }

    /**
     * AJAX: adószám ellenőrzése (NAV) és a már ismert partner adatainak lekérése a Billingóból.
     */
    public static function ajax_lookup_vat_number(): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!wcFlexibleIsTrue(get_option('wc_billingo_vat_number_form'))) {
            wp_send_json_error(['message' => ''], 403);
        }

        if (self::is_lookup_rate_limited()) {
            wp_send_json_error(['message' => __('Túl sok lekérdezés történt, kérjük, próbálja újra néhány perc múlva.', 'billingo')], 429);
        }

        $input = isset($_POST['vat_number']) ? sanitize_text_field(wp_unslash($_POST['vat_number'])) : '';

        wp_send_json_success(self::lookup_vat_number($input));
    }

    /**
     * Az adószám lekérdezésének tényleges logikája.
     *
     * A Billingo "check-tax-number" végpontja csak az érvényességet adja vissza (NAV-ellenőrzés), cégadatot nem.
     * A kitöltéshez ezért a bolt saját Billingo-partnerei között keresünk az adószámra: ha már számláztunk ennek a
     * cégnek, a neve és a címe kitölthető. A válasz szándékosan csak ezt a két adatot tartalmazza (e-mail,
     * telefon, megjegyzés nem), mert a végpont vendégeknek is elérhető.
     *
     * @param object|null $client BillingoClient (tesztben cserélhető)
     * @return array{status: string, message: string, company: ?array<string, string>}
     */
    public static function lookup_vat_number(string $input, ?object $client = null): array
    {
        $result = ['status' => 'unknown', 'message' => '', 'company' => null];

        $normalized = self::normalize_hungarian_vat_number($input);

        if ($normalized === null) {
            return ['status' => 'invalid_format', 'message' => __('Az adószám formátuma nem megfelelő.', 'billingo'), 'company' => null];
        }

        try {
            $client ??= new BillingoClient(get_option('wc_billingo_api_key', ''));

            if (wcFlexibleIsTrue(get_option('wc_billingo_vat_number_live_check'))) {
                $response = $client->util()->checkTaxNumber($normalized)->getResponse();

                if ($response->getStatusCode() === 200) {
                    $data = $response->getData();
                    $nav = $data instanceof TaxNumber ? $data->result : null;

                    if ($nav === CheckTaxNumberMessageEnum::VALIDATION_OK->value) {
                        $result['status'] = 'valid';
                        $result['message'] = __('Az adószám érvényes.', 'billingo');
                    } elseif (in_array($nav, [
                        CheckTaxNumberMessageEnum::INVALID_TAX_NUMBER->value,
                        CheckTaxNumberMessageEnum::NON_EXIST_TAX_NUMBER->value,
                    ], true)) {
                        return [
                            'status' => 'invalid',
                            'message' => __('A megadott adószám a NAV nyilvántartása szerint nem érvényes.', 'billingo'),
                            'company' => null,
                        ];
                    }
                }
            }

            if (wcFlexibleIsTrue(get_option('wc_billingo_vat_number_autofill'))) {
                $result['company'] = self::find_known_company($client, $normalized);
            }
        } catch (\Throwable $e) {
            // Fail-open: egy Billingo/NAV hiba nem akadályozhatja a vásárlást, a mezők kézzel kitölthetők.
            Billingo_Logger::error('Adószám-lekérdezés kivétel: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * A bolt Billingo-partnerei közül a megadott adószámú partner neve és címe (vagy null).
     * A találatot az adószám számjegyeinek egyezésével ellenőrizzük, mert a keresés szöveges.
     *
     * @return array<string, string>|null
     */
    private static function find_known_company(object $client, string $normalizedVatNumber): ?array
    {
        $digits = preg_replace('/\D/', '', $normalizedVatNumber);
        $company = null;

        $client->partner()->query()->whereQuery($normalizedVatNumber)->getData()->each(
            function ($partner) use ($digits, &$company) {
                if ($company !== null) {
                    return;
                }

                $data = $partner->toArray();

                if (preg_replace('/\D/', '', (string)($data['taxcode'] ?? '')) !== $digits || empty($data['name'])) {
                    return;
                }

                $address = (array)($data['address'] ?? []);

                $company = [
                    'name' => sanitize_text_field((string)$data['name']),
                    'country' => sanitize_text_field((string)($address['country_code'] ?? '')),
                    'postcode' => sanitize_text_field((string)($address['post_code'] ?? '')),
                    'city' => sanitize_text_field((string)($address['city'] ?? '')),
                    'address' => sanitize_text_field((string)($address['address'] ?? '')),
                ];
            }
        );

        return $company;
    }

    /**
     * Egy IP-címről érkező lekérdezések számát korlátozza (a végpont vendégeknek is elérhető).
     */
    private static function is_lookup_rate_limited(): bool
    {
        $ip = class_exists('WC_Geolocation') ? \WC_Geolocation::get_ip_address() : ($_SERVER['REMOTE_ADDR'] ?? '');
        $key = 'wc_billingo_vl_' . md5((string)$ip);
        $count = (int)get_transient($key);

        if ($count >= self::LOOKUP_LIMIT) {
            return true;
        }

        set_transient($key, $count + 1, self::LOOKUP_WINDOW);

        return false;
    }

    /**
     * A felhasználó által beírt (kötőjellel/anélkül, szóközökkel) adószámot a Billingo API
     * által elvárt "NNNNNNNN-N-NN" formátumra hozza. Null-t ad vissza, ha a bemenet nem
     * alakítható érvényes formátumra.
     */
    public static function normalize_hungarian_vat_number(string $vat_number): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $vat_number);

        if (strlen($digits) !== 11) {
            return null;
        }

        $formatted = substr($digits, 0, 8) . '-' . substr($digits, 8, 1) . '-' . substr($digits, 9, 2);

        return preg_match('/^\d{8}-[1-5]-\d{2}$/', $formatted) === 1 ? $formatted : null;
    }

    /**
     * Menti az adószámot a rendelés meta adataiba
     */
    public static function save_vat_number_to_order($order): void
    {
        if (!is_object($order) || empty($_POST['billing_vat_number'])) {
            return;
        }

        $vat_number = sanitize_text_field(wp_unslash($_POST['billing_vat_number']));
        // Magyar adószámot egységes NNNNNNNN-N-NN alakban mentünk (a partner adószámaként ez megy a Billingóba).
        $vat_number = self::normalize_hungarian_vat_number($vat_number) ?? $vat_number;

        $order->update_meta_data('adoszam', $vat_number);
        $order->update_meta_data('_billing_vat_number', $vat_number);
    }

    /**
     * Megjeleníti az adószámot az admin rendelés oldalon
     */
    public static function display_vat_number_in_admin($order): void
    {
        $vat_number = self::get_vat_number_from_order($order);

        if (!empty($vat_number)) {
            //echo '<p><strong>' . __('Adószám:', 'billingo') . '</strong> ' . esc_html($vat_number) . '</p>';
        }
    }

    /**
     * Megjeleníti az adószámot a rendelés részleteiben (frontend)
     */
    public static function display_vat_number_in_order_details($order): void
    {
        $vat_number = self::get_vat_number_from_order($order);

        if (!empty($vat_number)) {
            //echo '<tr><th>' . __('Adószám:', 'billingo') . '</th><td>' . esc_html($vat_number) . '</td></tr>';
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

        // Rendelés-meta API (nem get_post_meta): HPOS alatt a rendelés nem bejegyzés.
        // Először az "adoszam" meta mezőből próbáljuk
        $vat_number = $order->get_meta('adoszam', true);

        // Ha nincs, akkor a _billing_vat_number-ből
        if (empty($vat_number)) {
            $vat_number = $order->get_meta('_billing_vat_number', true);
        }

        // Ha van custom meta mező beállítva
        // (a kulcs aláhúzással és anélkül is mentődhet, mint a számlakészítésnél: TS-4917)
        $custom_field = trim((string)get_option('wc_billingo_vat_number_form_custom', ''));
        if ($custom_field !== '' && empty($vat_number)) {
            $bare_key = ltrim($custom_field, '_');

            foreach (array_unique([$custom_field, '_' . $bare_key, $bare_key]) as $candidate_key) {
                $candidate = $order->get_meta($candidate_key, true);

                if (!empty($candidate) && is_scalar($candidate)) {
                    $vat_number = $candidate;
                    break;
                }
            }
        }

        // HuCommerce kompatibilitás
        if (empty($vat_number) && self::is_hucommerce_active()) {
            $vat_number = $order->get_meta('_billing_tax_number', true);
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
    public static function save_product_original_prices($order_id, $from_status = null, $to_status = null, $order_object = null): void
    {

        Billingo_Logger::info('save_product_original_prices');

        $order = wc_get_order($order_id);
        if (!$order) {
            Billingo_Logger::info('Order not found for ID: ' . $order_id);
            return;
        }

        // Ellenőrizzük, hogy már mentettük-e ezt a rendelést. Rendelés-meta API (HPOS-kompatibilis); a korábbi
        // verziók a jelzőt bejegyzés-metába (post meta) írták, ezért azt is megnézzük: így a már feldolgozott
        // rendeléseknél nem mentődnek újra az árak a termék azóta megváltozott áraival.
        $already_saved = $order->get_meta('_billingo_original_prices_saved', true)
            ?: get_post_meta($order_id, '_billingo_original_prices_saved', true);
        //ha már el van mentve akkor nem mentünk rá újra, nehogy árban eltérés legyen a termék árának változása miatt
        if ($already_saved) {

            return;
        }

        Billingo_Logger::info('save_product_original_prices called for order ID: ' . $order_id);

        $items = $order->get_items();

        foreach ($items as $item_id => $item) {
            $product = $item->get_product();

            if ($product && method_exists($product, 'get_regular_price')) {
                $regular_price = $product->get_regular_price();
                $quantity = $item->get_quantity();
                $sale_price = $quantity > 0
                    ? ($item->get_subtotal() + $item->get_subtotal_tax()) / $quantity
                    : 0;
                if (!empty($regular_price)) {
                    // Metaadat mentése a rendelési tételhez
                    wc_add_order_item_meta($item_id, '_wc_billingo_product_full_price_without_sale', $regular_price);
                    wc_add_order_item_meta($item_id, '_wc_billingo_product_full_price_with_sale', $sale_price);
                    Billingo_Logger::info("Original prices saved for order {$order_id}, item {$item_id}: Regular={$regular_price}, Sale={$sale_price}");
                } else {
                    Billingo_Logger::info("Empty regular price for Product ID: {$product->get_id()}");
                }
            } else {
                Billingo_Logger::info("Product not found or get_regular_price method missing for item ID: {$item_id}");
            }
        }

        // Jelöljük, hogy már mentettük ezt a rendelést
        $order->update_meta_data('_billingo_original_prices_saved', 1);
        $order->save_meta_data();
    }
} 