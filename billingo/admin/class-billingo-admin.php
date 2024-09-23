<?php

if (!defined('WPINC')) {
    die();
}

use PWSBillingo\PWSBillingo;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

class Billingo_Admin
{
    /** @var bool */
    protected static $initialized = false;

    public static function init()
    {
        if (!self::$initialized) {
            if (class_exists('WC_Order')) {
                self::$initialized = true;

                self::init_hooks();
            } else {
                add_action('admin_notices', 'showNoticeMissingWoocommerce');

                function showNoticeMissingWoocommerce()
                {
                    echo('<div class="notice notice-error"><p>A Billingo plugin használatához szükséges a Woocommerce plugin megléte. Ha frissítés közben látja ezt az üzenetet, valószínűleg a rendszer átmenetileg kikapcsolta a bővítményt.</p></div>');
                }
            }
        }
    }

    public static function init_hooks()
    {
        add_action('admin_init', [__CLASS__, 'wc_billingo_admin_init']);
        add_action('add_meta_boxes', [__CLASS__, 'wc_billingo_add_metabox']);
        add_action('woocommerce_admin_order_actions_end', [__CLASS__, 'add_listing_actions']); // pre-hpos
        add_filter('woocommerce_shop_order_list_table_columns', [__CLASS__, 'woocommerce_shop_order_list_table_columns']); //post-hpos
        add_action('woocommerce_shop_order_list_table_custom_column', [__CLASS__, 'woocommerce_shop_order_list_table_custom_column'], 10, 2); //post-hpos

        add_filter('woocommerce_settings_tabs_array', [__CLASS__, 'add_settings_tab'], 50);
        add_action('woocommerce_settings_tabs_settings_tab_billingo', [__CLASS__, 'settings_tab']);
        add_action('woocommerce_update_options_settings_tab_billingo', [__CLASS__, 'update_settings']);

        add_action('woocommerce_admin_field_billingo_payment_settings_table', [__CLASS__, 'billingo_admin_field_billingo_payment_settings_table']);
        add_action('woocommerce_admin_field_billingo_email_settings_table', [__CLASS__, 'billingo_admin_field_billingo_email_settings_table']);
        add_action('woocommerce_admin_field_billingo_support_table', [__CLASS__, 'billingo_admin_field_billingo_support_table']);

        add_filter('woocommerce_admin_settings_sanitize_option_billingo_payment_settings_table', [__CLASS__, 'filter_billingo_update_option_billingo_payment_settings_table'], 10, 3);
        add_filter('woocommerce_admin_settings_sanitize_option_billingo_email_settings_table', [__CLASS__, 'filter_billingo_update_option_billingo_email_settings_table'], 10, 3);

        add_action('admin_notices', [__CLASS__, 'author_admin_notice']);
        add_action('admin_init', [__CLASS__, 'author_admin_notice_dismissed']);

        add_filter('plugin_action_links_billingo/index.php', [__CLASS__, 'addWpSettingsLink']);


        if (get_option('wc_billingo_use_product_id')) {
            add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'add_custom_field_to_single_product']);
            add_action('woocommerce_process_product_meta', [__CLASS__, 'save_custom_field_data_for_single_product']);
            add_action('woocommerce_product_after_variable_attributes', [__CLASS__, 'add_custom_field_to_variations'], 10, 3);
            add_action('woocommerce_save_product_variation', [__CLASS__, 'save_custom_field_data_for_variations'], 10, 2);
        }
    }

    /**
     * Adds notice to the admin panel
     */
    public static function author_admin_notice()
    {
        $url = add_query_arg(['billingo-notice-review-dismissed' => ''], 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);

        $user_id = get_current_user_id();
        if (!get_user_meta($user_id, 'billingo_notice_review_dismissed')) {
            echo('<div class="notice notice-info"><p>Elégedett vagy a Billingo bővítménnyel? Segíts másokat <a href="https://wordpress.org/plugins/billingo/#reviews" target="_blank">értékeléseddel</a>. Köszönjük! <a href="' . esc_url($url) . '">[Értesítés bezárása]</a></p></div>');
        }
    }

    /**
     * Sets meta not to display the notice after dismissed
     */
    public static function author_admin_notice_dismissed()
    {

        if (isset($_GET['billingo-notice-review-dismissed'])) {
            $user_id = get_current_user_id();
            add_user_meta($user_id, 'billingo_notice_review_dismissed', 'true', true);
        }
    }


    public static function addWpSettingsLink($links)
    {
        $url = esc_url(add_query_arg(['page' => 'wc-settings', 'tab' => 'settings_tab_billingo'], get_admin_url() . 'admin.php'));

        array_push($links, '<a href="' . $url . '">' . __('Settings') . '</a>');

        return $links;
    }

    /**
     * Enqueue admin assets
     */
    public static function wc_billingo_admin_init()
    {
        $plugin_data = get_plugin_data(BILLINGO__PLUGIN_DIR . 'index.php');
        $plugin_version = $plugin_data['Version'];

        wp_enqueue_script('billingo_js', plugins_url('/js/global.js', __FILE__), ['jquery'], $plugin_version);
        wp_enqueue_style('billingo_css', plugins_url('/css/global.css', __FILE__));

        $wc_billingo_local = ['loading' => plugins_url('/images/ajax-loader.gif', __FILE__)];
        wp_localize_script('billingo_js', 'wc_billingo_params', $wc_billingo_local);
    }

    /**
     * Adds Billingo tab to WooCommerce Settings page
     *
     * @param array $settings_tabs WooCommerce/Settings Tabs
     *
     * @return array $settings_tabs
     */
    public static function add_settings_tab($settings_tabs)
    {
        $settings_tabs['settings_tab_billingo'] = __('Billingo', 'woocommerce-settings-tab-billingo');
        return $settings_tabs;
    }

    /**
     * Outputs admin fields
     */
    public static function settings_tab()
    {
        woocommerce_admin_fields(self::get_settings());
    }

    /**
     * Updates all settings which are passed
     */
    public static function update_settings()
    {
        woocommerce_update_options(self::get_settings());
    }

    /**
     * Adds settings to WooCommerce/Settings/Billingo
     *
     * @return array
     */
    public static function get_settings()
    {
        $order_statuses = wc_get_order_statuses();

        $taxes = [-1 => ''];
        foreach (PWSBillingo::ALL_TAXES as $v) {
            $taxes[$v] = $v;
        }

        $entitlements = PWSBillingo::indexArray(PWSBillingo::ALL_ENTITLEMENTS);

        $bpms = [];
        foreach (PWSBillingo::ALL_PAYMENTS as $k => $v) {
            $bpms[$k] = __($v, 'billingo');
        }
        $langs = [];
        foreach (PWSBillingo::ALL_LANGUAGES as $k => $v) {
            $langs[$k] = __($v, 'billingo');
        }
        $roundings = [];
        foreach (PWSBillingo::ALL_ROUNDS as $k => $v) {
            $roundings[$k] = __($v, 'billingo');
        }

        $connector = WC_Billingo::getBillingoConnector();
        $bank_accounts = $connector->getBankAccounts();
        $document_blocks = $connector->getDocumentBlocks();
        $bank_accounts[''] = __('Alapértelmezett', 'billingo');
        $document_blocks[''] = __('Alapértelmezett', 'billingo');

        // if missing from the list, reset to default
        if ($invoice_block = get_option('wc_billingo_invoice_block')) {
            if (!array_key_exists($invoice_block, $document_blocks)) {
                update_option('wc_billingo_invoice_block', '');
            }
        }
        if ($bank_account = get_option('wc_billingo_bank_account_huf')) {
            if (!array_key_exists($bank_account, $bank_accounts)) {
                update_option('wc_billingo_bank_account_huf', '');
            }
        }
        if ($bank_account = get_option('wc_billingo_bank_account_eur')) {
            if (!array_key_exists($bank_account, $bank_accounts)) {
                update_option('wc_billingo_bank_account_eur', '');
            }
        }

        $settings = [
            ['type' => 'title', 'title' => __('Billingo API Beállítások', 'billingo'), 'id' => 'woocommerce_billingo_options'],
            [
                'title' => __('v3 API kulcs*', 'billingo'),
                'id'    => 'wc_billingo_api_key',
                'type'  => 'text',
                'desc'  => __('A Billingo vezérlőpultban az API menüben található.', 'billingo'),
            ],
            [
                'title'   => __('Számlatömb', 'billingo'),
                'id'      => 'wc_billingo_invoice_block',
                'type'    => 'select',
                'options' => $document_blocks,
                'desc'    => __('A számlázáshoz használandó számlatömb.', 'billingo'),
            ],
            ['type' => 'sectionend', 'id' => 'woocommerce_billingo_options'],
            ['type' => 'title', 'title' => __('Billingo Számla Beállítások', 'billingo'), 'id' => 'woocommerce_billingo_options'],
            [
                'title'   => __('Bankszámla HUF', 'billingo'),
                'id'      => 'wc_billingo_bank_account_huf',
                'type'    => 'select',
                'options' => $bank_accounts,
                'desc'    => __('Forint (HUF) számlákhoz használt bankszámla.', 'billingo'),
            ],
            [
                'title'   => __('Bankszámla EUR', 'billingo'),
                'id'      => 'wc_billingo_bank_account_eur',
                'type'    => 'select',
                'options' => $bank_accounts,
                'desc'    => __('Euró (EUR) számlákhoz használt bankszámla.', 'billingo'),
            ],
            [
                'title' => __('Proforma figyelmen kívül hagyása számla generálásakor', 'billingo'),
                'id'    => 'wc_billingo_disable_proforma_invoicing',
                'type'  => 'checkbox',
                'desc'  => __('Ha nincs bekapcsolva és létezik egy rendeléshez proforma, akkor abból készül éles számla. Ha gyakran történik proforma kiállítás után rendelés módosítás, akkor ezzel az opcióval tiltható a proforma használata, így friss adatokkal készül számla. (Manuális számlázásnál külön opcióval egyenként is tiltható.)'),
            ],
            [
                'title' => __('Megjegyzés', 'billingo'),
                'id'    => 'wc_billingo_note',
                'type'  => 'text',
            ],
            [
                'title' => __('Megrendelés azonosító hozzáadása a megjegyzéshez', 'billingo'),
                'id'    => 'wc_billingo_note_orderid',
                'type'  => 'checkbox',
            ],
            [
                'title' => __('Barion tranzakció azonosító hozzáadása a megjegyzéshez', 'billingo'),
                'id'    => 'wc_billingo_note_barion',
                'type'  => 'checkbox',
                'desc'  => __('(Barion plugin használata esetén)'),
            ],
            [
                'title' => __('Cikkszám hozzáadása a tételek megjegyzéseihez', 'billingo'),
                'id'    => 'wc_billingo_sku',
                'type'  => 'checkbox',
            ],
            [
                'title' => __('Elektronikus számla', 'billingo'),
                'id'    => 'wc_billingo_electronic',
                'type'  => 'checkbox',
            ],
            [
                'title' => __('Pénzügyi teljesítést nem igényel jelölés', 'billingo'),
                'id'    => 'mark_paid_without_financial_fulfillment',
                'type'  => 'checkbox',
                'desc'  => __('Fizetettnek jelölt számlákon a "A számla pénzügyi teljesítést nem igényel." jelölés bekapcsolása.'),
            ],
            [
                'title'   => __('Manuális számlakészítés típus', 'billingo'),
                'id'      => 'wc_billingo_manual_type',
                'type'    => 'select',
                'options' => [
                    'invoice'  => __('Számla', 'billingo'),
                    'proforma' => __('Proforma', 'billingo'),
                    'draft'    => __('Piszkozat', 'billingo'),
                ],
                'desc'    => __('Manuális számlakészítés esetén ez a típus lesz kiválasztva.', 'billingo'),
            ],
            [
                'title'   => __('Automata számlakészítés', 'billingo'),
                'id'      => 'wc_billingo_auto',
                'type'    => 'select',
                'options' => [
                    'no'    => __('Nem', 'billingo'),
                    'yes'   => __('Igen, rendes számla', 'billingo'),
                    'draft' => __('Igen, de csak piszkozat', 'billingo'),
                ],
                'desc'    => __('Ha be van kapcsolva, akkor a rendelés alább beállított állapotra váltásakor automatikusan kiállításra kerül a számla, vagy piszkozat készül.', 'billingo'),
            ],
            [
                'title'   => __('Számlakészítés rendelés állapot', 'billingo'),
                'id'      => 'wc_billingo_auto_state',
                'type'    => 'select',
                'options' => $order_statuses,
                'desc'    => __('Ha feljebb be van kapcsolva az automata számlakészítés, akkor ebbe a rendelés állapotba váltáskor fog számla vagy piszkozat készülni.', 'billingo'),
            ],
            [
                'title' => __('Alrendelés számlázás tiltása', 'billingo'),
                'id' => 'wc_billingo_block_child_orders',
                'type' => 'checkbox',
                'desc' => __('Bekapcsolva az alrendelésekről nem készülhet számla.', 'billingo'),
            ],
            [
                'title'   => __('Automata sztornózás', 'billingo'),
                'id'      => 'wc_billingo_auto_storno',
                'type'    => 'select',
                'options' => array_merge(['no' => __('Nem', 'billingo')], $order_statuses),
                'desc'    => __('Ha be van kapcsolva, a kiválasztott rendelés állapotba váltáskor automatikusan sztornózásra kerül az éles számla, ha már létezett.', 'billingo'),
            ],
            [
                'title'   => __('Díjbekérő létrehozása', 'billingo'),
                'id'      => 'wc_billingo_payment_request_auto',
                'type'    => 'select',
                'options' => [
                    'no'    => __('Nem', 'billingo'),
                    'yes'   => __('Igen, díjbekérő', 'billingo'),
                    'draft' => __('Igen, de csak piszkozat', 'billingo'),
                ],
                'desc'    => __('Ha be van kapcsolva, akkor a rendelés létrejöttekor automatán kiállításra kerül egy díjbekérő (vagy piszkozat készül).', 'billingo'),
            ],
            [
                'title' => __('Piszkozatnál "küldés Billingon keresztül" megjelölés', 'billingo'),
                'id'    => 'wc_billingo_draft_enable_send',
                'type'  => 'checkbox',
                'desc'  => __('Ha be van kapcsolva, akkor piszkozat számláknál beállításra kerül a "Küldés Billingon keresztül" opció.', 'billingo'),
            ],
            [
                'title' => __('Alapértelmezett fizetési mód', 'billingo'),
                'id' => 'wc_billingo_fallback_payment',
                'type' => 'select',
                'options' => $bpms,
                'default' => PWSBillingo::DEFAULT_PAYMENT,
                'desc' => __('Ez akkor kerül használatra, ha a rendeléshez nincs fizetési mód társítva.', 'billingo') . ' ' . __('Például, ha kupon felhasználás közben 0 Ft-ra jön ki a rendelés végösszege, megadhatod, hogy Kupon fizetési móddal jöjjön létre a bizonylat.', 'billingo'),
            ],
            [
                'title'   => __('Számla nyelve', 'billingo'),
                'id'      => 'wc_billingo_invoice_lang',
                'type'    => 'select',
                'options' => $langs,
                'default' => 'hu',
            ],
            [
                'title' => __('Számla a rendelés nyelvén', 'billingo'),
                'id'    => 'wc_billingo_invoice_lang_wpml',
                'type'  => 'checkbox',
                'desc'  => __('Bekapcsolva a rendeléskor használt nyelven kerül kiállításra a számla. WPML és WooCommerce Multilingual pluginok szükségesek ehhez a funkcióhoz.', 'billingo'),
            ],
            [
                'title'   => __('Kerekítés', 'billingo'),
                'id'      => 'wc_billingo_invoice_round',
                'type'    => 'select',
                'options' => $roundings,
            ],

            [
                'title' => __('Mennyiségi egység', 'billingo'),
                'id'    => 'wc_billingo_unit',
                'type'  => 'text',
                'desc'  => __('Ha meg van adva, erre cserélődik az alapértelmezett "db" mennyiségi egység a termékeknél.', 'billingo'),
            ],

            [
                'title'   => __('Árazás', 'billingo'),
                'id'      => 'wc_billingo_pricing',
                'type'    => 'select',
                'options' => [
                    0 => __('Bruttó', 'billingo'),
                    1 => __('Nettó', 'billingo'),
                ],
                'desc' => __('A választott ár kerül átadásra a Billingo felé. (Átszámítás miatti kerekítési hibák elkerülésére.)', 'billingo'),
            ],
            [
                'title' => __('Keresztnév és vezetéknév felcserélése', 'billingo'),
                'id'    => 'wc_billingo_flip_name',
                'type'  => 'checkbox',
            ],
            [
                'title' => __('Cégnév feltűntetése', 'billingo'),
                'id'    => 'wc_billingo_company_name',
                'type'  => 'select',
                'options' => [
                    0 => __('Cégnév + Név', 'billingo'),
                    1 => __('Csak cégnév', 'billingo'),
                ],
            ],
            [
                'title' => __('Adattörlő kódot tartalmazó egyedi meta mező neve.', 'billingo'),
                'id'    => 'wc_billingo_is_generate_erase_code',
                'type'  => 'text',
            ],
            ['type' => 'sectionend', 'id' => 'woocommerce_billingo_options'],
            ['type' => 'title', 'title' => __('Adószám Beállítások', 'billingo'), 'id' => 'woocommerce_billingo_tax_number_options'],
            [
                'title' => __('Adószám mező megjelenítése vásárláskor', 'billingo'),
                'id'    => 'wc_billingo_vat_number_form',
                'type'  => 'checkbox',
                'desc'  => __('A számlázási adatok alján egy új mező kerül hozzáadásra, melybe az adószámot kérjük be. Ez automatikusan rákerül a számlára is. A rendelés adataiban tároljuk, ha kézzel kell megadni (utólag) a rendeléskezelőben, akkor az egyedi mezőknél egy "adoszam" mezőt kell kitölteni.', 'billingo')
                    . ' ' . __('HuCommerce plugin használata esetén ne kapcsolja be, az adószám automatikusan átadásra kerül, amennyiben a vásárló megadta.', 'billingo'),
            ],
            [
                'title' => __('Egyedi meta mezőt használok adószámhoz', 'billingo'),
                'id'    => 'wc_billingo_vat_number_form_checkbox_custom',
                'type'  => 'checkbox',
                'desc'  => __('(pl.: WooCheckout vagy Custom WooCommerce Checkout Fields Editor pluginnel). Fixen meg fog jelenni a számlákon. Ha ezt az opciót használja, akkor a fentebb található "Adószám mező megjelenítése vásárláskor" opció bekapcsolása nem szükséges.', 'billingo'),
            ],
            [
                'title' => __('Adószámot tartalmazó egyedi meta mező neve.', 'billingo'),
                'id'    => 'wc_billingo_vat_number_form_custom',
                'type'  => 'text',
                'desc'  => __('A felhasználóhoz tartozó, adószámot tartalmazó meta mező neve az adatbázisban (pl.: billing_adoszam, vagy billing_myfield5).', 'billingo'),
            ],
            [
                'title'    => __('Adószám figyelmeztetés', 'billingo'),
                'id'      => 'wc_billingo_vat_number_notice',
                'type'    => 'text',
                'default' => __('Az adószám megadása kötelező magyar adóalanyok esetében, ezért amennyiben rendelkezik adószámmal, azt kötelező megadni a számlázási adatoknál.', 'billingo'),
                'desc'    => __('Ez az üzenet jelenik meg felül a fizetés oldalon, ha az "Adószám mező megjelenítése vásárláskor" opció be van kapcsolva.', 'billingo'),
            ],
            ['type' => 'sectionend', 'id' => 'woocommerce_billingo_tax_number_options'],
            ['type' => 'title', 'title' => __('ÁFA Felülírás Beállítások', 'billingo'), 'id' => 'woocommerce_billingo_tax_override_options'],
            [
                'title'   => __('ÁFA felülírás', 'billingo'),
                'id'      => 'wc_billingo_tax_override',
                'type'    => 'select',
                'options' => $taxes,
                'desc'    => __('ÁFA érték kicserélése globálisan a kiválasztottra (ha nem üres).', 'billingo'),
            ],
            [
                'title'   => __('ÁFA felülíráshoz tartozó jogcím', 'billingo'),
                'id'      => 'wc_billingo_tax_override_entitlement',
                'type'    => 'select',
                'options' => $entitlements, // Reloads in global.js
                'desc'    => __('A választott ÁFA kulcshoz kötelező a megadott jogcímekből választani.', 'billingo'),
            ],
            [
                'title'   => __('ÁFA felülírás, ha 0%', 'billingo'),
                'id'      => 'wc_billingo_tax_override_zero',
                'type'    => 'select',
                'options' => $taxes,
                'desc'    => __('Ha 0%-ra jön ki az ÁFA, akkor legyen kicserélve a kiválasztottra.', 'billingo'),
            ],
            [
                'title'   => __('0% ÁFA felülíráshoz tartozó jogcím', 'billingo'),
                'id'      => 'wc_billingo_tax_override_zero_entitlement',
                'type'    => 'select',
                'options' => $entitlements, // Reloads in global.js
                'desc'    => __('A választott ÁFA kulcshoz kötelező a megadott jogcímekből választani.', 'billingo'),
            ],
            [
                'title' => __('ÁFA felülírás, EU/EUK', 'billingo'),
                'id'    => 'wc_billingo_tax_override_eu',
                'type'  => 'checkbox',
                'desc'  => __('Ha a megrendelő országa EU-s ország, akkor "EU", ha azon kívüli, akkor "EUK" ÁFA kulcs kerül használatra. (Az alábbi opciót kapcsolja be a szállító felülírásához!)', 'billingo'),
            ],
            [
                'title' => __('ÁFA felülírás szállítóknál is', 'billingo'),
                'id'    => 'wc_billingo_tax_override_include_carrier',
                'type'  => 'checkbox',
                'desc'  => __('A szállítási díjnál is kicserélődjön-e az ÁFA. (Pl.: termékeknél legyen felülírás \'AM\'-re, de a szállítás 27% maradjon, akkor NE jelölje be ezt a mezőt.)', 'billingo'),
            ],
            [
                'title' => __('Szállító mindig látszódjon', 'billingo'),
                'id'    => 'wc_billingo_always_add_carrier',
                'type'  => 'checkbox',
                'desc'  => __('Bekapcsolva a szállító 0 összegű tételként is rákerül a számlára.', 'billingo'),
            ],
            ['type' => 'sectionend', 'id' => 'woocommerce_billingo_tax_override_options'],
            ['type' => 'billingo_email_settings_table', 'id' => 'billingo_email_settings_table'],
            ['type' => 'billingo_payment_settings_table', 'id' => 'billingo_payment_settings_table'],
            ['type' => 'billingo_support_table', 'id' => 'billingo_support_table'],
        ];

        return apply_filters('wc_settings_tab_billingo_settings', $settings);
    }

    /**
     * Generates Billingo payment settings table
     *
     * @param $value
     */
    public static function billingo_admin_field_billingo_payment_settings_table($value)
    {
        $payment_methods = WC_Billingo::get_available_payment_methods();
        $billingo_payment_methods = PWSBillingo::ALL_PAYMENTS;
        ?>
        <h2><?php esc_html_e('Fizetési módok beállításai', 'billingo'); ?></h2>
        <table class="billingo_payment_settings_table wc_input_table sortable widefat">
            <thead>
            <tr>
                <th width="20px"><?php esc_html_e('Fizetési mód (helyi)', 'billingo'); ?></th>
                <th><?php esc_html_e('Billingo-beli megfelelője', 'billingo'); ?></th>
                <th><?php esc_html_e('Fizetési határidő', 'billingo'); ?></th>
                <th><?php esc_html_e('Fizetettnek jelölés (Díjbekérő esetén)', 'billingo'); ?></th>
                <th><?php esc_html_e('Fizetettnek jelölés (Éles számla esetén)', 'billingo'); ?></th>
                <th><?php esc_html_e('Díjbekérő kiállítása*', 'billingo'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($payment_methods as $k => $name) {
                $bpm = get_option('wc_billingo_payment_method_' . $k);
                $due = (int)get_option('wc_billingo_paymentdue_' . $k, 0);
                $pay = (int)get_option('wc_billingo_mark_as_paid_' . $k, 0);
                $pay2 = (int)get_option('wc_billingo_mark_as_paid2_' . $k, 0);
                $pro = (int)get_option('wc_billingo_proforma_' . $k, 0);
                ?>
                <tr>
                    <th>
                        <?php echo(esc_html($name)); ?>
                    </th>
                    <td>
                        <select name="billingo_payment_settings[<?php echo(esc_attr($k)); ?>][billingo_payment_method]">
                            <option> <?php esc_html_e('-- Válassz! --', 'billingo'); ?> </option>
                            <?php foreach ($billingo_payment_methods as $bpv => $bp_name): ?>
                                <option value="<?php echo(esc_attr($bpv)); ?>" <?php selected($bpm, $bpv); ?>><?php esc_html_e($bp_name, 'billingo'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input type="number" name="billingo_payment_settings[<?php echo(esc_attr($k)); ?>][paymentdue]" value="<?php echo(esc_attr($due)); ?>" />
                    </td>
                    <td align="center">
                        <input type="hidden" name="billingo_payment_settings[<?php echo(esc_attr($k)); ?>][mark_as_paid2]" value="0" />
                        <input type="checkbox" name="billingo_payment_settings[<?php echo(esc_attr($k)); ?>][mark_as_paid2]" value="1" <?php checked($pay2) ?>/>
                    </td>
                    <td align="center">
                        <input type="hidden" name="billingo_payment_settings[<?php echo(esc_attr($k)); ?>][mark_as_paid]" value="0" />
                        <input type="checkbox" name="billingo_payment_settings[<?php echo(esc_attr($k)); ?>][mark_as_paid]" value="1" <?php checked($pay); ?>/>
                    </td>
                    <td align="center">
                        <input type="hidden" name="billingo_payment_settings[<?php echo(esc_attr($k)); ?>][proforma]" value="0" />
                        <input type="checkbox" name="billingo_payment_settings[<?php echo(esc_attr($k)); ?>][proforma]" value="1" <?php checked($pro); ?>/>
                    </td>
                </tr>
                <?php
            }
            ?>
            </tbody>
        </table>
        <small>
            *: <?php esc_html_e('Csak ha fent be van kapcsolva a díjbekérő kiállítása', 'billingo'); ?>
            .
        </small>
        <?php
    }

    /**
     * Generates E-mail settings table
     */
    public static function billingo_admin_field_billingo_email_settings_table()
    {
        $options = [
            'no' => __('Nem', 'billingo'),
            'yes' => __('Küldés külön E-mailben', 'billingo'),
            'attach' => __('Csatolás a WooCommerce E-mailhez', 'billingo'),
            'both' => __('Mindkét előző opció', 'billingo'),
        ];

        $email_invoice = get_option('wc_billingo_email', 'no');
        $email_proforma = get_option('wc_billingo_proforma_email', 'no');
        $email_storno = get_option('wc_billingo_storno_email', 'no');

        $btn_text_proforma = get_option('wc_billingo_proforma_email_woo_btn', __('Díjbekérő letöltése', 'billingo'));
        $btn_text_invoice = get_option('wc_billingo_email_woo_btn', __('Számla letöltése', 'billingo'));
        $btn_text_storno = get_option('wc_billingo_storno_email_woo_btn', __('Storno számla letöltése', 'billingo'));

        $text_proforma = get_option('wc_billingo_proforma_email_woo_text', __('Díjbekérője elkészült, melyet az alábbi linken tud letölteni.', 'billingo'));
        $text_invoice = get_option('wc_billingo_email_woo_text', __('Számlája elkészült, melyet az alábbi linken tud letölteni.', 'billingo'));
        $text_storno = get_option('wc_billingo_storno_email_woo_text', __('Storno számlája elkészült, melyet az alábbi linken tud letölteni.', 'billingo'));
        ?>
        <h2><?php esc_html_e('E-mail Beállítások', 'billingo'); ?></h2>
        <table class="billingo_email_settings_table wc_input_table sortable widefat">
            <thead>
            <tr>
                <th></th>
                <th><?php esc_html_e('Díjbekérő', 'billingo'); ?></th>
                <th><?php esc_html_e('Számla', 'billingo'); ?></th>
                <th><?php esc_html_e('Storno', 'billingo'); ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <th class="th-border-bottom th-border-right"><?php esc_html_e('Küldés', 'billingo'); ?></th>
                <td class="td-padding">
                    <select name="billingo_email_settings[wc_billingo_proforma_email]">
                        <?php foreach ($options as $key => $val): ?>
                            <option value="<?php echo(esc_attr($key)); ?>" <?php selected($key, $email_proforma); ?>><?php echo(esc_html($val)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="td-padding">
                    <select name="billingo_email_settings[wc_billingo_email]">
                        <?php foreach ($options as $key => $val): ?>
                            <option value="<?php echo(esc_attr($key)); ?>" <?php selected($key, $email_invoice); ?>><?php echo(esc_html($val)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="td-padding">
                    <select name="billingo_email_settings[wc_billingo_storno_email]">
                        <?php foreach ($options as $key => $val): ?>
                            <option value="<?php echo(esc_attr($key)); ?>" <?php selected($key, $email_storno); ?>><?php echo(esc_html($val)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th class="th-border-bottom th-border-right">
                    <?php esc_html_e('Gomb szöveg', 'billingo'); ?>
                    <br>
                    <small><?php esc_html_e('Ez lesz a gomb szövege az emailben,<br>ha a csatolás be van kapcsolva.', 'billingo'); ?></small>
                </th>
                <td>
                    <input type="text" class="regular-text" name="billingo_email_settings[wc_billingo_proforma_email_woo_btn]" value="<?php echo(esc_attr($btn_text_proforma)); ?>"/>
                </td>
                <td>
                    <input type="text" class="regular-text" name="billingo_email_settings[wc_billingo_email_woo_btn]" value="<?php echo(esc_attr($btn_text_invoice)); ?>"/>
                </td>
                <td>
                    <input type="text" class="regular-text" name="billingo_email_settings[wc_billingo_storno_email_woo_btn]" value="<?php echo(esc_attr($btn_text_storno)); ?>"/>
                </td>
            </tr>
            <tr>
                <th class="th-border-bottom th-border-right">
                    <?php esc_html_e('Gomb feletti szöveg', 'billingo'); ?>
                    <br>
                    <small><?php esc_html_e('Ez az üzenet jelenik meg az emailben,<br>ha a csatolás be van kapcsolva.', 'billingo'); ?></small>
                </th>
                <td>
                    <input type="text" class="regular-text" name="billingo_email_settings[wc_billingo_proforma_email_woo_text]" value="<?php echo(esc_attr($text_proforma)); ?>"/>
                </td>
                <td>
                    <input type="text" class="regular-text" name="billingo_email_settings[wc_billingo_email_woo_text]" value="<?php echo(esc_attr($text_invoice)); ?>"/>
                </td>
                <td>
                    <input type="text" class="regular-text" name="billingo_email_settings[wc_billingo_storno_email_woo_text]" value="<?php echo(esc_attr($text_storno)); ?>"/>
                </td>
            </tr>
            <tr>
                <th class="th-border-right"><?php esc_html_e('WooCommerce template', 'billingo'); ?></th>
                <td class="td-padding">processing</td>
                <td class="td-padding">completed</td>
                <td class="td-padding">refunded</td>
            </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * Generates Billingo support section
     *
     * @param $value
     */
    public static function billingo_admin_field_billingo_support_table($value)
    {
        global $wp_version;
        global $woocommerce;

        $plugin_data = get_plugin_data(BILLINGO__PLUGIN_DIR . 'index.php');
        $plugin_version = $plugin_data['Version'];

        $debug_data = [
            'Plugin_version' => $plugin_version,
            'WP_version' => $wp_version,
            'WC_version' => $woocommerce->version,
        ];
        $logfile = ABSPATH . 'wp-content/uploads/billingo/billingo_' . date('Y-m-d') . '.txt';

        list($debug_normal, $debug) = PWSBillingo::getDebugData($debug_data);

        ?>
        <h2 style="margin-top: 32px;"><?php esc_html_e('Támogatás', 'billingo'); ?></h2>
        <table class="form-table">
            <tbody>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="wc_billingo_support_code"><?php esc_html_e('Hibakeresést segítő kód', 'billingo'); ?></label>
                </th>
                <td class="forminp forminp-text">
                    <textarea name="wc_billingo_support_code" id="wc_billingo_support_code" readonly="readonly" title="<?php echo(esc_attr($debug_normal)); ?>" rows="7"><?php echo(esc_html($debug)); ?></textarea>
                    <br/>
                    <span class="description">Kérjük kapcsolatfelvételkor küldje el nekünk a mező teljes tartalmát <strong>szövegként</strong>. Ebben néhány fontos információ található, például a telepített plugin verziószáma, PHP verzió, Wordpress verzió, stb. A pontos részletekhez tartsa az egeret a kód fölé.</span>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="wc_billingo_support_code"><?php esc_html_e('Naplófájlok', 'billingo'); ?>:</label>
                </th>
                <td class="forminp forminp-text">

                    <textarea readonly="readonly" rows="10" cols="100"><?php echo file_get_contents( $logfile ) ?></textarea>
                    <br/>
                    <span class="description"><?php esc_html_e('Mindig az adott napi log-ot tartalmazza!', 'billingo'); ?></span>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="wc_billingo_support_code"><?php esc_html_e('Elérhetőség', 'billingo'); ?>:</label>
                </th>
                <td class="forminp forminp-text">
                    <a href="mailto:hello@billingo.hu">hello@billingo.hu</a>
                </td>
            </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * Updates payment settings
     * @param $value
     * @return false if no setting to be updated
     */
    public static function filter_billingo_update_option_billingo_payment_settings_table($value, $option, $raw_value)
    {
        $billingo_payment_settings = $_POST['billingo_payment_settings'];

        if (!is_array($billingo_payment_settings)) {
            return false;
        }

        foreach ($billingo_payment_settings as $id => $fields) {
            $id = sanitize_text_field($id);
            update_option('wc_billingo_payment_method_' . $id, sanitize_text_field($fields['billingo_payment_method']));
            update_option('wc_billingo_paymentdue_' . $id, (int)$fields['paymentdue']);
            update_option('wc_billingo_mark_as_paid_' . $id, (int)$fields['mark_as_paid']);
            update_option('wc_billingo_mark_as_paid2_' . $id, (int)$fields['mark_as_paid2']);
            update_option('wc_billingo_proforma_' . $id, (int)$fields['proforma']);
        }
    }

    /**
     * Updates email settings
     * @param $value
     * @return false if no setting to be updated
     */
    public static function filter_billingo_update_option_billingo_email_settings_table($value, $option, $raw_value)
    {
        $billingo_email_settings = $_POST['billingo_email_settings'];

        if (!is_array($billingo_email_settings)) {
            return false;
        }

        foreach ($billingo_email_settings as $key => $value) {
            update_option(sanitize_text_field($key), sanitize_text_field($value));
        }
    }

    /**
     * Adds icon to order list to show invoice
     *
     * @param object $order object which for the icon should be placed
     */
    public static function add_listing_actions($order)
    {
        $order_id = $order->get_id();

        if (WC_Billingo::isInvoiceGenerated($order_id)) {
            ?>
            <a href="<?php echo(esc_url(WC_Billingo::generateDownloadLink($order_id, PWSBillingo::INVOICE_TYPE_INVOICE))); ?>" class="button tips wc_billingo" target="_blank" data-tip="<?php esc_html_e('Billingo számla', 'billingo'); ?>">
                <img src="<?php echo(esc_url(BILLINGO__PLUGIN_URL . 'admin/images/invoice.png')); ?>" alt="" width="16" height="16" />
            </a>
            <?php
        }

        if (WC_Billingo::isProformaGenerated($order_id)) {
            ?>
            <a href="<?php echo(esc_url(WC_Billingo::generateDownloadLink($order_id, PWSBillingo::INVOICE_TYPE_PROFORMA))); ?>" class="button tips wc_billingo" target="_blank" data-tip="<?php esc_html_e('Billingo díjbekérő', 'billingo'); ?>">
                <img src="<?php echo(esc_url(BILLINGO__PLUGIN_URL . 'admin/images/payment_request.png')); ?>" alt="" width="16" height="16" />
            </a>
            <?php
        }
    }

    public static function woocommerce_shop_order_list_table_columns($columns)
    {
        $columns['billingo_column'] = 'Billingo';
        return $columns;
    }

    public static function woocommerce_shop_order_list_table_custom_column($column, $order)
    {
        if ('billingo_column' !== $column) {
            return;
        }

        static::add_listing_actions($order);
    }

    public static function wc_billingo_add_metabox($post)
    {
        $screen = wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id('shop-order') : 'shop_order';
        add_meta_box('custom_order_option', 'Billingo számla', [__CLASS__, 'render_meta_box_content'], $screen, 'side');
    }

    /**
     * Renders meta box content
     *
     * @param object $post_or_order_object gives the data of the post object to be used
     */
    public static function render_meta_box_content($post_or_order_object)
    {
        $order = ($post_or_order_object instanceof WP_Post) ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;

        if (!get_option('wc_billingo_api_key')) { ?>
            <p style="text-align: center;"><?php esc_html_e('A számlakészítéshez meg kell adnod a Billingo API kulcsokat a Woocommerce beállításokban!', 'billingo'); ?></p>
        <?php } else { ?>
            <div id="wc-billingo-messages"></div>
            <?php if ($order->get_meta('_wc_billingo_own', true)): ?>
                <div style="text-align:center;" id="billingo_already_div">
                    <?php $note = $order->get_meta('_wc_billingo_own', true); ?>
                    <p>
                        <?php esc_html_e('A számlakészítés ki lett kapcsolva, mert: ', 'billingo'); ?>
                        <strong><?php esc_html_e($note); ?></strong><br/>
                        <a id="wc_billingo_already_back" href="#" data-nonce="<?php echo(esc_attr(wp_create_nonce('wc_already_invoice'))); ?>" data-order="<?php echo(esc_attr($order->get_id())); ?>"><?php esc_html_e('Visszakapcsolás', 'billingo'); ?></a>
                    </p>
                </div>
            <?php endif; ?>
            <?php
            $document_proforma = WC_Billingo::findOrderInvoice($order->get_id(), PWSBillingo::INVOICE_TYPE_PROFORMA);
            $dijbekero_szamlaszam = false;
            if (is_array($document_proforma) && array_key_exists('billingo_id', $document_proforma) && $document_proforma['billingo_id']) {
                $dijbekero_szamlaszam = $document_proforma['billingo_number'];
            }
            ?>
            <?php if ($dijbekero_szamlaszam): ?>
                <p>
                    <?php esc_html_e('Díjbekérő', 'billingo'); ?>
                    <span class="alignright">
                        <?php echo(esc_html($dijbekero_szamlaszam)); ?>
                        -
                        <a href="<?php echo(esc_url(WC_Billingo::generateDownloadLink($order->get_id(), PWSBillingo::INVOICE_TYPE_PROFORMA))); ?>" target="_blank">
                            <?php esc_html_e('Letöltés', 'billingo'); ?>
                        </a>
                    </span>
                </p>
                <hr/>
            <?php endif; ?>

            <?php
            $document_storno = WC_Billingo::findOrderInvoice($order->get_id(), PWSBillingo::INVOICE_TYPE_STORNO);
            $szamlaszam = false;
            if (is_array($document_storno) && array_key_exists('billingo_id', $document_storno) && $document_storno['billingo_id']) {
                $szamlaszam = $document_storno['billingo_number'];
            }
            ?>
            <?php if ($szamlaszam): ?>
                <div style="text-align:center;">
                    <p><?php esc_html_e('Storno számla sikeresen létrehozva.', 'billingo'); ?></p>
                    <p>
                        <?php esc_html_e('A storno számla sorszáma:', 'billingo'); ?>
                        <strong><?php echo(esc_html($szamlaszam)); ?></strong>
                    </p>
                    <p>
                        <a href="<?php echo(esc_url(WC_Billingo::generateDownloadLink($order->get_id(), PWSBillingo::INVOICE_TYPE_STORNO))); ?>" id="wc_billingo_download" class="button button-primary" target="_blank">
                            <?php esc_html_e('Storno Számla megtekintése', 'billingo'); ?>
                        </a>
                    </p>
                </div>
            <?php elseif (WC_Billingo::isInvoiceGenerated($order->get_id()) && !$order->get_meta('_wc_billingo_own', true)): ?>
                <div style="text-align:center;">
                    <p><?php esc_html_e('Számla sikeresen létrehozva.', 'billingo'); ?></p>
                    <p>
                        <?php esc_html_e('A számla sorszáma:', 'billingo'); ?>
                        <strong><?php echo(esc_html(WC_Billingo::getInvoiceNumberGenerated($order->get_id()))); ?></strong>
                    </p>
                    <p>
                        <a href="<?php echo(esc_url(WC_Billingo::generateDownloadLink($order->get_id(), PWSBillingo::INVOICE_TYPE_INVOICE))); ?>" id="wc_billingo_download" class="button button-primary" target="_blank">
                            <?php esc_html_e('Számla megtekintése', 'billingo'); ?>
                        </a>
                    </p>
                    <?php if ($order->get_meta('_wc_billingo_storno', true) != 1) { ?>
                        <p>
                            <a href="#" id="wc_billingo_storno" data-order="<?php echo(esc_attr($order->get_id())); ?>" data-nonce="<?php echo(esc_attr(wp_create_nonce('wc_storno_invoice'))); ?>" class="button button-primary"><?php esc_html_e('Sztornózás', 'billingo'); ?></a>
                        </p>
                    <?php } ?>
                </div>
            <?php else: ?>
                <?php $default_type = get_option('wc_billingo_manual_type', 'invoice'); ?>
                <div style="text-align:center;<?php if ($order->get_meta('_wc_billingo_own', true)): ?>display:none;<?php endif; ?>" id="wc-billingo-generate-button">
                    <p>
                        <a href="#" id="wc_billingo_generate" data-order="<?php echo(esc_attr($order->get_id())); ?>" data-nonce="<?php echo(esc_attr(wp_create_nonce('wc_generate_invoice'))); ?>" class="button button-primary"><?php esc_html_e('Számlakészítés', 'billingo'); ?></a>
                        <br/>
                        <small>
                            Alapértelmezett típus: <?php esc_html_e($default_type == 'draft' ? 'Piszkozat' : ($default_type == 'invoice' ? 'Számla' : 'Proforma'), 'billingo'); ?>
                        </small>
                        <br/>
                        <a href="#" id="wc_billingo_options"><?php esc_html_e('Opciók', 'billingo'); ?></a>
                    </p>
                    <div id="wc_billingo_options_form" style="display:none;">
                        <div class="fields">
                            <?php if ($dijbekero_szamlaszam) { ?>
                                <h4><?php esc_html_e('Díjbekérő figyelmen kívül hagyása', 'billingo'); ?></h4>
                                <input type="checkbox" id="wc_billingo_ignore_proforma" value="1" />
                                <small><?php esc_html_e('Ha a rendelés módosítva lett a díjbekérő kiállítása óta, akkor ezt a mezőt jelölje be, hogy a számla friss adatokkal készüljön a korábbi díjbekérő helyett.', 'billingo'); ?></small>
                            <?php } ?>

                            <h4><?php esc_html_e('Megjegyzés', 'billingo'); ?></h4>
                            <input type="text" id="wc_billingo_invoice_note" value="<?php esc_attr_e(get_option('wc_billingo_note')); ?>" />

                            <h4><?php esc_html_e('Fizetési határidő (nap)', 'billingo'); ?></h4>
                            <input type="number" step="1" id="wc_billingo_invoice_deadline" />

                            <h4><?php esc_html_e('Teljesítési dátum', 'billingo'); ?></h4>
                            <input type="text" class="date-picker" id="wc_billingo_invoice_completed" maxlength="10" value="<?php echo(wp_date('Y-m-d')); ?>" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])" />

                            <h4><?php esc_html_e('Számla típusa', 'billingo'); ?></h4>

                            <select name="wc_billingo_invoice_type" id="wc_billingo_invoice_type">
                                <option value="invoice" <?php selected($default_type, 'invoice') ?>><?php esc_html_e('Számla', 'billingo'); ?></option>
                                <option value="proforma" <?php selected($default_type, 'proforma') ?>><?php esc_html_e('Díjbekérő', 'billingo'); ?></option>
                                <option value="draft" <?php selected($default_type, 'draft') ?>><?php esc_html_e('Piszkozat', 'billingo'); ?></option>
                            </select>
                        </div>
                        <a id="wc_billingo_already" href="#" data-nonce="<?php echo(esc_attr(wp_create_nonce('wc_already_invoice'))); ?>" data-order="<?php echo(esc_attr($order->get_id())); ?>">
                            <?php esc_html_e('Számlakészítés kikapcsolása', 'billingo'); ?>
                        </a>
                    </div>
                    <?php if (get_option('wc_billingo_auto') == 'yes'): ?>
                        <p>
                            <small><?php esc_html_e('A számla automatikusan elkészül és el lesz küldve a vásárlónak, a megfelelő rendelésállapot beállításakor.', 'billingo'); ?></small>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif;
        }
    }


    public static function add_custom_field_to_single_product()
    {
        global $post;

        if (is_object($post) && $post->post_type === 'product') {
            $product = wc_get_product($post);
            if ($product->is_type('simple')) {
                woocommerce_wp_text_input([
                    'id' => '_wc_billingo_remote_id',
                    'label' => 'Billingo távoli termék ID',
                    'desc_tip' => 'true',
                    'description' => 'Enter the Remote Product ID for this product.',
                ]);
            }
        }
    }

    public static function add_custom_field_to_variations($loop, $variation_data, $variation)
    {
        woocommerce_wp_text_input([
            'id' => '_wc_billingo_remote_id_' . $variation->ID,
            'label' => 'Billingo távoli termék ID',
            'desc_tip' => 'true',
            'description' => 'Enter the Remote Product ID for this variation.',
            'value' => get_post_meta($variation->ID, '_wc_billingo_remote_id', true),
        ]);
    }

    public static function save_custom_field_data_for_single_product($post_id)
    {
        $remote_id = sanitize_text_field($_POST['_wc_billingo_remote_id']);
        update_post_meta($post_id, '_wc_billingo_remote_id', $remote_id);
    }

    public static function save_custom_field_data_for_variations($variation_id, $i)
    {
        $remote_id = sanitize_text_field($_POST['_wc_billingo_remote_id_' . $variation_id]);
        update_post_meta($variation_id, '_wc_billingo_remote_id', $remote_id);
    }
}
