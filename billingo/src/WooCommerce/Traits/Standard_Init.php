<?php

namespace App\Billingo\WooCommerce\Traits;

use App\Billingo\Enums\Document\TypeEnum;
use App\Billingo\Enums\PaymentStatusEnum;
use App\Billingo\Service\BillingoClient;
use App\Billingo\WooCommerce\Controllers\Billingo_Controller;
use App\Billingo\WooCommerce\Repositories\Billingo_Repositroy;
use App\Billingo\WooCommerce\Service\Billingo_Document_Generator;
use App\Billingo\WooCommerce\Service\Billingo_Logger;
use App\Billingo\WooCommerce\Service\Invoice_Generation_Container;
use App\Billingo\WooCommerce\Service\Billingo_Checkout_Fields;
use Symfony\Component\HttpFoundation\Response;
use WC_Order;

trait Standard_Init
{

    public static function init_hooks(): void
    {
        foreach (wc_get_order_statuses() as $status => $name) {
            $status = str_replace('wc-', '', $status);
            add_action('woocommerce_order_status_' . $status, [self::class, 'on_order_state_change'], 10);
        }
        new WC_Order();
        //add_action('woocommerce_thankyou_order_received_text', [self::class, 'action_woocommerce_email_before_order_table'], 10, 1);
        add_action('wp_ajax_wc_billingo_generate_invoice', [self::class, 'ajax_generateInvoice']);
        add_action('wp_ajax_wc_billingo_storno_invoice', [self::class, 'ajax_stornoInvoice']);
        add_action('wp_ajax_wc_billingo_check_payment_status', [self::class, 'ajax_checkPaymentStatus']);
        add_action('wp_ajax_wc_billingo_toggle_invoicing', [self::class, 'ajax_toggleInvoicing']);
        add_action('woocommerce_email_before_order_table', [self::class, 'action_woocommerce_email_before_order_table'], 1, 4);
        //add_action('woocommerce_email_before_order_table', [self::class, 'action_woocommerce_email_before_order_table'], 20, 4);

        // 1 thankyou-ra should-proforma generate by payment method státusztól függetlenül
        add_action('woocommerce_thankyou', [self::class, 'should_proforma_generate'], 10, 1);
        // 2 automata díjbekérőt csak akkor generáljunk, ha az automata számla generálás státusz


        // Initialize product price saving hooks (always needed for orders)
        Billingo_Checkout_Fields::init_product_price_hooks();

        // Pénztár oldali adószám mező (és élő lekérdezés): eddig sosem hívódott meg, ezért a hozzá tartozó
        // beállítások hatástalanok voltak. Csak a bekapcsolt beállításnál köt hookot.
        Billingo_Checkout_Fields::init_vat_fields();

        // Az eredeti/kedvezményes ár mentése továbbra is megtörténik (a számlázási logika
        // ezt használja), de ne jelenjen meg a rendelés részletes nézetében (admin és
        // frontend/thank-you oldalon sem).
        add_filter('woocommerce_hidden_order_itemmeta', [self::class, 'hide_billingo_price_order_item_meta']);

        // Átutalásos (bacs) rendelések fizetési státuszának időzített ellenőrzése a
        // Billingo felé. A tényleges munkát a cron callback végzi el, ott dől el (a
        // beállítás alapján), hogy ténylegesen fusson-e le bármi.
        if (!wp_next_scheduled('billingo_check_bacs_payment_status')) {
            wp_schedule_event(time(), 'hourly', 'billingo_check_bacs_payment_status');
        }
        add_action('billingo_check_bacs_payment_status', [self::class, 'check_bacs_payment_status']);
    }

    /**
     * "Feldolgozásra vár" (on-hold) állapotú rendeléseknél, azoknál a fizetési módoknál,
     * amelyeknél a Fizetési módok beállításban célállapot van megadva, lekérdezi a
     * Billingo-tól a hozzájuk tartozó számla fizetési státuszát, és ha az kifizetettként
     * szerepel, automatikusan a beállított célállapotra váltja a WooCommerce rendelést.
     */
    public static function check_bacs_payment_status(): void
    {
        if (!wcFlexibleIsTrue(get_option('wc_billingo_bacs_payment_check_enabled'))) {
            return;
        }

        Billingo_Logger::info('Fizetési státusz ellenőrzés elindult (cron).');

        foreach (self::get_payment_methods_with_check_status() as $paymentMethodId => $targetStatus) {
            $orderIds = wc_get_orders([
                'status' => 'on-hold',
                'payment_method' => $paymentMethodId,
                'limit' => -1,
                'return' => 'ids',
            ]);

            if (empty($orderIds)) {
                continue;
            }

            foreach ($orderIds as $orderId) {
                self::check_single_order_payment_status((int)$orderId, $targetStatus);
            }
        }
    }

    /**
     * A Fizetési módok beállításban megadott, "Fizetés ellenőrzés célállapota" mezővel
     * rendelkező fizetési módokat adja vissza, [fizetési_mód_id => célállapot] alakban.
     */
    private static function get_payment_methods_with_check_status(): array
    {
        $result = [];

        foreach (WC()->payment_gateways->payment_gateways() as $gateway) {
            $targetStatus = get_option('wc_billingo_payment_check_status_' . $gateway->id, '');

            if (!empty($targetStatus)) {
                $result[$gateway->id] = $targetStatus;
            }
        }

        return $result;
    }

    /**
     * Egyetlen rendelés fizetési státuszát ellenőrzi a Billingo-nál, és ha kifizetettként
     * szerepel, a megadott célállapotra váltja. Mind a cron, mind a kézi ellenőrző gomb
     * ezt a közös logikát használja.
     *
     * @return array{success: bool, paid: bool, message: string}
     */
    private static function check_single_order_payment_status(int $orderId, string $targetStatus): array
    {
        $repository = new Billingo_Repositroy();

        $row = $repository
            ->where('order_id', $orderId)
            ->where('type', TypeEnum::INVOICE->value)
            ->first();

        // Az előlegszámla -> végszámla automatizmusnál a fizetés-ellenőrzés célállapotát
        // még a végszámla legenerálása ELŐTT éri el a rendelés, ilyenkor még csak
        // előlegszámla létezik — ennek a fizetési státuszát ellenőrizzük helyette.
        if (is_null($row) || empty($row['billingo_id'])) {
            $row = $repository
                ->where('order_id', $orderId)
                ->where('type', TypeEnum::ADVANCE->value)
                ->first();
        }

        if (is_null($row) || empty($row['billingo_id'])) {
            return ['success' => false, 'paid' => false, 'message' => __('Nincs érvényes számla ehhez a rendeléshez.', 'billingo')];
        }

        $client = new BillingoClient(get_option('wc_billingo_api_key'));

        try {
            $response = $client->document()->getById((int)$row['billingo_id'])->getResponse();
        } catch (\Exception $e) {
            Billingo_Logger::error('Fizetési státusz lekérdezés hiba (rendelés #' . $orderId . '): ' . $e->getMessage());

            return ['success' => false, 'paid' => false, 'message' => __('Hiba történt a Billingo API hívása közben.', 'billingo')];
        }

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            return ['success' => false, 'paid' => false, 'message' => __('A Billingo nem adott érvényes választ a számla lekérdezésére.', 'billingo')];
        }

        $document = $response->getData();

        if (!is_object($document) || !method_exists($document, 'toArray')) {
            return ['success' => false, 'paid' => false, 'message' => __('A Billingo válasza nem tartalmazza a számla adatait.', 'billingo')];
        }

        if (($document->payment_status ?? null) !== PaymentStatusEnum::PAID->value) {
            return ['success' => true, 'paid' => false, 'message' => __('A számla a Billingo szerint még nincs kifizetve.', 'billingo')];
        }

        $order = wc_get_order($orderId);

        if (!$order) {
            return ['success' => false, 'paid' => true, 'message' => __('A rendelés nem található.', 'billingo')];
        }

        $targetSlug = str_replace('wc-', '', $targetStatus);

        // A lekérdezés alatt (vagy a kézi gomb miatt) már lezárt/érvénytelen rendelést nem szabad visszavinni
        // a célállapotba (pl. egy visszatérített vagy lemondott rendelés ne váljon újra "feldolgozás alattivá"),
        // és az már a célállapotban lévő rendelést sem módosítjuk újra (felesleges állapotváltás-hookok).
        if ($order->has_status(['cancelled', 'refunded', 'failed', 'trash'])) {
            return ['success' => true, 'paid' => true, 'message' => __('A számla kifizetett, de a rendelés jelenlegi állapota miatt az állapota nem módosult.', 'billingo')];
        }

        if ($order->has_status($targetSlug)) {
            return ['success' => true, 'paid' => true, 'message' => __('A számla kifizetve, a rendelés már a célállapotban van.', 'billingo')];
        }

        $order->update_status(
            $targetSlug,
            __('A Billingo szerint a számla kifizetésre került, a rendelés állapota automatikusan frissült.', 'billingo')
        );

        Billingo_Logger::info("Rendelés #{$orderId} állapota frissítve ({$targetStatus}) - Billingo szerint kifizetve.");

        return ['success' => true, 'paid' => true, 'message' => __('A számla kifizetve, a rendelés állapota frissült.', 'billingo')];
    }

    /**
     * Kézi "Fizetés ellenőrzése" gomb AJAX kezelője a rendelés oldalon.
     */
    public static function ajax_checkPaymentStatus(): void
    {
        check_ajax_referer('wc_storno_invoice', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'billingo'));
        }

        if (!isset($_POST['order'])) {
            wp_send_json_error(['error' => true, 'messages' => [__('A rendelés ID nincs megadva', 'billingo')]]);
            return;
        }

        $orderId = (int)$_POST['order'];
        $order = wc_get_order($orderId);

        if (!$order) {
            wp_send_json_error(['error' => true, 'messages' => [__('A rendelés nem található', 'billingo')]]);
            return;
        }

        $paymentMethod = $order->get_payment_method();
        $targetStatus = get_option('wc_billingo_payment_check_status_' . $paymentMethod, '');

        if (empty($targetStatus)) {
            wp_send_json_error([
                'error' => true,
                'messages' => [__('Ehhez a fizetési módhoz nincs célállapot beállítva a Fizetési módok oldalon.', 'billingo')],
            ]);
            return;
        }

        $result = self::check_single_order_payment_status($orderId, $targetStatus);

        if (!$result['success']) {
            wp_send_json_error(['error' => true, 'messages' => [$result['message']]]);
            return;
        }

        wp_send_json_success(['error' => false, 'messages' => [$result['message']]]);
    }

    /**
     * A rendelés oldali „Számlázás kikapcsolása ehhez a rendeléshez” kapcsoló AJAX kezelője.
     */
    public static function ajax_toggleInvoicing(): void
    {
        check_ajax_referer('wc_storno_invoice', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(['error' => true, 'messages' => [__('Nincs jogosultságod ehhez a művelethez.', 'billingo')]]);
            return;
        }

        $orderId = isset($_POST['order']) ? (int)$_POST['order'] : 0;
        $order = $orderId > 0 ? wc_get_order($orderId) : false;

        if (!$order instanceof WC_Order) {
            wp_send_json_error(['error' => true, 'messages' => [__('A rendelés nem található', 'billingo')]]);
            return;
        }

        $disabled = wcFlexibleIsTrue(sanitize_text_field(wp_unslash($_POST['disabled'] ?? '')));

        Billingo_Document_Generator::setInvoicingDisabledForOrder($order, $disabled);

        $message = $disabled
            ? __('A számlázás ki van kapcsolva ehhez a rendeléshez: automatikusan és kézzel sem készül bizonylat.', 'billingo')
            : __('A számlázás vissza van kapcsolva ehhez a rendeléshez.', 'billingo');

        // A változás nyoma a rendelés megjegyzései között (ki és mikor kapcsolta).
        $order->add_order_note('Billingo: ' . $message);
        Billingo_Logger::info('Számlázás kapcsoló módosítva, rendelés #' . $orderId . ': ' . ($disabled ? 'kikapcsolva' : 'bekapcsolva'));

        wp_send_json_success(['error' => false, 'disabled' => $disabled, 'messages' => [$message]]);
    }

    /**
     * A Billingo által mentett eredeti/kedvezményes ár meta mezőket hozzáadja a
     * WooCommerce "rejtett" rendelési tétel meta listájához, hogy azok az admin és a
     * frontend rendelés-részletes nézetben se jelenjenek meg.
     */
    public static function hide_billingo_price_order_item_meta(array $hidden): array
    {
        $hidden[] = '_wc_billingo_product_full_price_without_sale';
        $hidden[] = '_wc_billingo_product_full_price_with_sale';

        return $hidden;
    }

    /**
     * Processes automatic document operations
     *
     * @param integer $order_id ID of the order that is linked to the document
     */
    public static function on_order_state_change($order_id): void
    {
        // A WooCommerce csak az Exception-t fogja el az állapotváltás hookoknál: egy Error/TypeError itt a
        // fizetési webhookot (vagy a pénztár/köszönő oldalt) is leállítaná. A számlázás hibája soha nem
        // állíthatja meg a rendelés feldolgozását — naplózzuk, és a rendelésen megjegyzésben jelezzük.
        try {
            self::process_order_state_change($order_id);
        } catch (\Throwable $e) {
            Billingo_Logger::error('on_order_state_change váratlan hiba (rendelés #' . $order_id . '): ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());

            $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
            if ($order instanceof WC_Order) {
                $order->add_order_note(__('Billingo: az automatikus számlázás váratlan hibával megszakadt: ', 'billingo') . $e->getMessage());
            }
        }
    }

    private static function process_order_state_change($order_id): void
    {
        Billingo_Logger::info('on_order_state_change triggered for order ID: ' . $order_id);
        $invoice_generation_data = self::collect_invoice_generation_data($order_id);

        // Generate proforma/draft if needed, removed because of the thankyou hook
        //self::should_proforma_generate($order_id);

        if (Billingo_Document_Generator::isInvoicingDisabledForOrder($invoice_generation_data->getOrder())) {
            // A rendelés oldalán ki van kapcsolva a számlázás: automatikus bizonylat nem készül.
            // (A lenti sztornózás ettől függetlenül lefut, a már kiállított számlát nem érinti.)
            Billingo_Logger::info('Automatikus számlázás kihagyva: ki van kapcsolva ehhez a rendeléshez. Rendelés ID: ' . $order_id);
        } elseif (self::should_generate_advance_invoice_for_pipeline($invoice_generation_data)) {
            // Az előlegszámla-összekapcsolás kapcsoló be van kapcsolva: a számlázást
            // aktiváló állapot elérésekor a beállított bizonylattípus helyett mindig
            // előlegszámlát állítunk ki, a végszámla csak a fizetés-ellenőrzés
            // célállapotába kerüléskor készül el.
            Billingo_Logger::info('Előlegszámla generálása a számlázási állapot elérésekor. Rendelés ID: ' . $order_id);
            self::generate_automatically($invoice_generation_data, 'getAdvance');
        } elseif (self::should_generate_final_invoice_from_advance($invoice_generation_data)) {
            // A rendeléshez már van érvényes előlegszámla, és a rendelés éppen most érte
            // el a fizetési módjához beállított, fizetés-ellenőrzés utáni célállapotot:
            // a végszámlát generáljuk le, amely a Billingón automatikusan az előleghez
            // lesz kapcsolva.
            Billingo_Logger::info('Végszámla generálása előlegszámla alapján, állapotváltás miatt. Rendelés ID: ' . $order_id);
            self::generate_automatically($invoice_generation_data, 'getInvoice');
        } elseif (self::should_document_generate($invoice_generation_data)) {
            self::generate_automatically($invoice_generation_data, 'get');
        }

        if (self::should_cancel_document($invoice_generation_data)) {
            Billingo_Logger::info('should_cancel_document triggered for order ID: - ' . $order_id);
            Billingo_Logger::startDocumentum();
            $billingo_document = $invoice_generation_data->getRepositroy()
                ->where('type', TypeEnum::INVOICE->value)
                ->where('order_id', $order_id)
                ->first();

            if (!is_null($billingo_document)) {
                $cancelled = $invoice_generation_data->getController()->cancelDocument($billingo_document['billingo_id']);

                if (!$cancelled) {
                    self::note_billingo_failure(
                        $invoice_generation_data->getOrder(),
                        __('az automatikus sztornózás nem sikerült', 'billingo'),
                        $invoice_generation_data->getController()->getLastError()
                    );
                }
            }

            Billingo_Logger::endDocumentum();
        }
    }

    /**
     * Egy automatikus bizonylatkészítés közös menete: a generátor adott metódusa, majd a Billingo-beli
     * létrehozás. Ha a bizonylat nem készül el, és az nem szándékos beállítás miatt van (kikapcsolt számlázás),
     * az okot megjegyzésben rögzítjük a rendelésen — korábban csak a naplófájlba került, a boltos pedig
     * nem látta, hogy a számla elmaradt.
     */
    private static function generate_automatically(Invoice_Generation_Container $invoice_generation_data, string $generatorMethod): void
    {
        Billingo_Logger::startDocumentum();

        $generator = $invoice_generation_data->getDocumentGenerator();
        $document = $generator->$generatorMethod();

        if (is_null($document)) {
            if (!$generator->wasSkippedByDesign()) {
                self::note_billingo_failure($invoice_generation_data->getOrder(), __('a bizonylat nem készült el', 'billingo'), $generator->getLastError());
            }
        } else {
            $controller = $invoice_generation_data->getController();

            if (is_null($controller->createDocument($document))) {
                self::note_billingo_failure($invoice_generation_data->getOrder(), __('a bizonylat létrehozása nem sikerült', 'billingo'), $controller->getLastError());
            }
        }

        Billingo_Logger::endDocumentum();
    }

    private static function note_billingo_failure(WC_Order $order, string $what, ?string $reason): void
    {
        $order->add_order_note('Billingo: ' . $what . ($reason ? ' – ' . $reason : '') . '.');
    }

    /**
     * Megnézzük, hogy be van -e kapcsolva a díjbekérő vagy piszkozat generálás, ha igen akkor a rendeléshez tartozó fizetési módnál megnézzük, hogy be van -e kapcsolva a díjbekérő generálása. Ha mindkét opció be van kapcsolva, akkor a díjbekérőt generál.
     *
     * @param integer $order_id ID of the order that is linked to the document
     */
    public static function should_proforma_generate($order_id): void
    {
        // A woocommerce_thankyou hookon fut: egy itt kiszabaduló Error a vevő köszönő oldalát döntené el.
        try {
            self::process_proforma_generation($order_id);
        } catch (\Throwable $e) {
            Billingo_Logger::error('should_proforma_generate váratlan hiba (rendelés #' . $order_id . '): ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    private static function process_proforma_generation($order_id): void
    {
        $order = wc_get_order($order_id);

        if (!($order instanceof WC_Order)) {
            return;
        }

        // Ha a rendeléshez már van (nem sztornózott) számla vagy előlegszámla, díjbekérő/piszkozat már nem
        // készülhet: a köszönő oldal későbbi megnyitása korábban a kiállított számla UTÁN is díjbekérőt hozott
        // létre, amit a rendelés oldal aztán "legutóbbi bizonylatként" mutatott, elrejtve a sztornó gombot.
        $issued = (new Billingo_Repositroy())
            ->where('order_id', (string)$order_id)
            ->get() ?? [];

        foreach ($issued as $row) {
            if (in_array($row['type'] ?? null, [TypeEnum::INVOICE->value, TypeEnum::ADVANCE->value], true)) {
                Billingo_Logger::info('Díjbekérő/piszkozat kihagyva: a rendeléshez már van számla vagy előlegszámla. Rendelés ID: ' . $order_id);

                return;
            }
        }

        // A rendelés oldalán kikapcsolt számlázásnál díjbekérő/piszkozat sem készül.
        if (Billingo_Document_Generator::isInvoicingDisabledForOrder($order)) {
            Billingo_Logger::info('Díjbekérő/piszkozat kihagyva: ki van kapcsolva a számlázás ehhez a rendeléshez. Rendelés ID: ' . $order_id);

            return;
        }

        $invoice_generation_data = self::collect_invoice_generation_data($order_id);

        $payment_method_proforma_enabled = false;

        //megnézzük, hogy szeretne-e egyáltalán generálni díjbekérőt a felhasználó
        if (get_option('wc_billingo_payment_request_auto') !== 'no') {
            $payment_method = $order->get_payment_method();
            Billingo_Logger::info('Checking payment method for proforma: ' . $payment_method);

            // ellenőrizzük, hogy a fizetési módnál be van -e kapcsolva a díjbekérő generálása
            $payment_method_proforma_setting = get_option('wc_billingo_proforma_' . $payment_method);
            $payment_method_proforma_enabled = wcFlexibleIsTrue($payment_method_proforma_setting);
        }

        //ha be van kapcsolva a díjbekérő generálása és a fizetési módnál be van kapcsolva a díjbekérő generálása akkor megnézzük, hogy már generálva van -e a díjbekérő vagy piszkozat,
        //ez azért fontos, mert ha már van generálva, akkor nem generáljuk le újra, hogy ne legyen több díjbekérő a rendeléshez, mert a woocommerce_thankyou hook meghívódhat az oldal frissítésekor is
        if ($payment_method_proforma_enabled) {
            $type = get_option('wc_billingo_payment_request_auto') === TypeEnum::PROFORMA->value
                ? 'getProforma'
                : 'getDraft';
            $billingo_document = (new Billingo_Repositroy())
                ->where('order_id', $order_id)
                ->where('type', get_option('wc_billingo_payment_request_auto') === TypeEnum::PROFORMA->value
                    ? TypeEnum::PROFORMA->value
                    : TypeEnum::DRAFT->value)
                ->first();

            //ha nincs még generálva a díjbekérő vagy piszkozat, akkor generáljuk
            if (!$billingo_document) {
                Billingo_Logger::info( $type . ' generating for payment method: ' . $payment_method);

                Billingo_Logger::startDocumentum();
                $document = $invoice_generation_data->getDocumentGenerator()->$type();

                if (!is_null($document)) {
                    $invoice_generation_data->getController()->createDocument($document);
                }

                Billingo_Logger::endDocumentum();
            } else {
                //ha már generálva van a díjbekérő vagy piszkozat, akkor nem generáljuk újra
                Billingo_Logger::info( $type . ' is already generated for payment method: ' . $payment_method);
            }


        }
    }

    public static function ajax_stornoInvoice(): void
    {
        check_ajax_referer('wc_storno_invoice', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'billingo'));
        }

        $billingo_repository = new Billingo_Repositroy();
        if(!isset($_POST['order'])) {
            wp_send_json_error(['error' => true, 'messages' => __('A rendelés ID nincs megadva', 'billingo')]);
        } else {
            $order_id = (int)$_POST['order'];
        }

        // Get the order to retrieve the billing email
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['error' => true, 'messages' => __('A rendelés nem található', 'billingo')]);
        }

        $row_in_db = $billingo_repository
            ->where('order_id', $order_id)
            ->where('type', TypeEnum::INVOICE->value)
            ->first();

        // Ha nincs helyi rekord (pl. "Duplicate vendor id" hiba miatt — a bizonylat
        // létrejött a Billingo oldalán, de a helyi mentés valamiért kimaradt), az admin
        // manuálisan megadhatja a Billingo felületén látott bizonylat ID-ját, hogy
        // ellenőrizni és szinkronizálni tudjuk, ahelyett hogy teljesen elakadna. Ez a
        // funkció külön beállítással ki-/bekapcsolható; a szerver oldalon is ellenőrizzük
        // (nem csak a felületen rejtjük el), hogy egy közvetlen POST kéréssel se lehessen
        // megkerülni, ha a boltos kikapcsolta.
        $manual_billingo_id = (isset($_POST['manual_billingo_id']) && wcFlexibleIsTrue(get_option('wc_billingo_manual_id_check_enabled')))
            ? (int)sanitize_text_field(wp_unslash($_POST['manual_billingo_id']))
            : 0;

        if ((is_null($row_in_db) || empty($row_in_db['billingo_id'])) && empty($manual_billingo_id)) {
            wp_send_json_error([
                'error'    => true,
                'messages' => [ __('Nem található érvényes számla ehhez a rendeléshez', 'billingo') ]
            ]);
            return;
        }

        $client = new BillingoClient(get_option('wc_billingo_api_key'));

        // Nincs helyi rekord, de van manuálisan megadott ID: itt NEM kezdeményezünk API-n
        // keresztüli sztornózást (mert nem tudjuk biztosan, hogy a megadott ID valóban
        // ehhez a rendeléshez tartozik) — csak leellenőrizzük a Billingón, hogy a bizonylat
        // sztornózott állapotú-e, és ha igen, elmentjük helyileg, hogy új számla készülhessen.
        if (is_null($row_in_db) && !empty($manual_billingo_id)) {
            $byIdData = $client->document()->getById($manual_billingo_id)->getResponse()->getData();

            if (!$byIdData || !is_object($byIdData) || !method_exists($byIdData, 'toArray')) {
                wp_send_json_error([
                    'error'    => true,
                    'messages' => [ __('A megadott azonosítóval nem található bizonylat a Billingóban. Ellenőrizd, hogy helyesen írtad-e be az ID-t.', 'billingo') ]
                ]);
                return;
            }

            if (empty($byIdData->toArray()['cancelled'])) {
                wp_send_json_error([
                    'error'    => true,
                    'messages' => [ __('A megadott bizonylat a Billingo szerint még nincs sztornózva. Sztornózd a Billingo felületén, majd próbáld újra.', 'billingo') ]
                ]);
                return;
            }

            $new_db_row = $billingo_repository->createFromDocument($order_id, $byIdData);

            if (is_null($new_db_row)) {
                wp_send_json_error([
                    'error'    => true,
                    'messages' => [ __('A bizonylat sztornózva van a Billingóban, de a helyi mentés sikertelen volt.', 'billingo') ]
                ]);
                return;
            }

            $billingo_repository->update($new_db_row['id'], ['canceled_by' => 999999999]);
            Billingo_Logger::info('Manual reconciliation: Billingo document ID ' . $manual_billingo_id . ' confirmed cancelled and synced locally for order ' . $order_id);

            wp_send_json_success([
                'error'    => false,
                'messages' => [ __('A megadott bizonylat sztornózva van a Billingóban — a helyi nyilvántartás frissült, új bizonylat kiállítható.', 'billingo') ]
            ]);
            return;
        }

        // A kézzel megadott ID csak a fenti, helyi sor nélküli egyeztetéshez való, és ott nem sztornóz.
        // Ha van helyi sor, mindig annak a bizonylatát sztornózzuk: a beküldött ID-val korábban bármely
        // (akár más rendeléshez tartozó) Billingo-bizonylat sztornózható volt.
        $billingo_id = $row_in_db['billingo_id'];
        $response = ['error' => false];

        //handles the email sending for the storno because it is not a document genaration, just a cancellation request to the server
        if(in_array(get_option('wc_billingo_storno_email'), ['both', 'billingo'])) {
            // Cím nélküli rendelésnél (pl. kézi rendelés) üres címzettet nem küldünk a Billingónak, mint a
            // Billingo_Controller teszi: egy üres cancellation_recipients elutasíthatja a sztornót.
            $cancellationData = $order->get_billing_email()
                ? ['cancellation_recipients' => $order->get_billing_email()]
                : [];

            Billingo_Logger::info('Cancelling invoice with email notification to: ' . $order->get_billing_email());

            $billingoResponse = $client->document()->cancelDocument($billingo_id, $cancellationData)->getResponse();
        }else{
            $billingoResponse = $client->document()->cancelDocument($billingo_id)->getResponse();
        }

        if ($billingoResponse->getStatusCode() === Response::HTTP_OK ) {
            $data = $billingoResponse->getData();


            $response['messages'][] =
                __('Számla sztornózva: ', 'billingo') . $data->invoice_number;

            $billingo_repository->update($row_in_db['id'], ['canceled_by' => $data->id]);
            $new_db_row = $billingo_repository->createFromDocument($order_id, $data);
            Billingo_Logger::info('Invoice cancel data: ' . json_encode($data->toArray()));

            Billingo_Logger::info('Invoice cancel SUCCESFULL:' . $new_db_row['id']);
        }else if ( ($byIdData = $client->document()->getById($billingo_id)->getResponse()->getData())
            && is_object($byIdData)
            && method_exists($byIdData, 'toArray')
            && !empty($byIdData->toArray()['cancelled']) ){
            $response['messages'][] =
                __('Számla sztornózva a Billingóban: ', 'billingo');

            $billingo_repository->update($row_in_db['id'], ['canceled_by' => 999999999]);

            Billingo_Logger::info('Invoice already cancelled!');
        }else {
            Billingo_Logger::error('Invoice cancel FAILED:' . json_encode($billingoResponse->getErrors()));

            wp_send_json_error([
                'error'    => true,
                'messages' => [ __('A számla sztornózása sikertelen', 'billingo') ]
            ]);
        }

        wp_send_json_success($response);
    }

    public static function ajax_generateInvoice(): void
    {
        check_ajax_referer('wc_storno_invoice', 'nonce');

        if (!current_user_can('edit_shop_orders')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'billingo'));
        }
        if(!isset($_POST['order'])) {
            wp_send_json_error(['error' => true, 'messages' => __('A rendelés ID nincs megadva', 'billingo')]);
        } else {
            $orderId = (int)$_POST['order'];
            $manualOrder = wc_get_order($orderId);

            // Érvénytelen azonosítóra (törölt rendelés, termék/oldal ID, visszatérítés) nem írunk meta adatot,
            // és a generátor sem indulhat el: hamis rendelésen annak konstruktora fatal errort dobna.
            if (!($manualOrder instanceof WC_Order)) {
                wp_send_json_error(['error' => true, 'messages' => [__('A rendelés nem található', 'billingo')]]);
                return;
            }

            // Rendelés-meta API (HPOS-kompatibilis), nem közvetlen post meta.
            $manualOrder->update_meta_data('_is_manual', 'yes');
            $manualOrder->save();
        }

        Billingo_Logger::info('ajax_generateInvoice raw POST data for wc_billingo_invoice_type: ' . ($_POST['wc_billingo_invoice_type'] ?? 'NOT SET'));
        Billingo_Logger::info('ajax_generateInvoice full relevant POST data: ' . json_encode([
                'order' => $_POST['order'] ?? null,
                'wc_billingo_invoice_type' => $_POST['wc_billingo_invoice_type'] ?? null,
                'wc_billingo_invoice_note' => $_POST['wc_billingo_invoice_note'] ?? null,
                'wc_billingo_invoice_deadline' => $_POST['wc_billingo_invoice_deadline'] ?? null,
                'wc_billingo_invoice_completed' => $_POST['wc_billingo_invoice_completed'] ?? null,
            ]));

        $response['error'] = false;

        $manualIncome = [];
        if (isset($_POST['wc_billingo_invoice_type']) && !empty($_POST['wc_billingo_invoice_type'])) {
            $manualIncome['invoice_type'] = sanitize_text_field(wp_unslash($_POST['wc_billingo_invoice_type']));
        }
        if (isset($_POST['wc_billingo_invoice_note'])) {
            $manualIncome['note'] = sanitize_text_field(wp_unslash($_POST['wc_billingo_invoice_note']));
        }
        if (isset($_POST['wc_billingo_invoice_deadline']) && !empty($_POST['wc_billingo_invoice_deadline'])) {
            $manualIncome['deadline'] = (int)$_POST['wc_billingo_invoice_deadline'];
        }
        if (isset($_POST['wc_billingo_invoice_completed']) && !empty($_POST['wc_billingo_invoice_completed'])) {
            $manualIncome['completed'] = sanitize_text_field(wp_unslash($_POST['wc_billingo_invoice_completed']));
        }

        $generator = new Billingo_Document_Generator($orderId, $manualIncome);
        $invoice = $generator->get();
        if (is_null($invoice)) {
            $response['error'] = true;
            $response['messages'] = [
                __('Sikertelen generálás', 'billingo'),
                $generator->getLastError() ?? __('A rendelés adatai hiányosak, vagy hibát tartalmaznak', 'billingo'),
            ];
        } else {
            $controller = new Billingo_Controller($orderId);
            $created = $controller->createDocument($invoice);

            if (is_null($created)) {
                $response['error'] = true;
                $response['messages'] = [
                    __('Sikertelen generálás', 'billingo'),
                    $controller->getLastError() ?? __('A rendelés adatai hiányosak, vagy hibát tartalmaznak', 'billingo'),
                ];
            } else {
                $response['messages'] = [
                    __('Sikeres generálás', 'billingo'),
                    __('A bizonylat száma:', 'billingo') . $created->invoice_number,
                ];

                $document = (new Billingo_Repositroy())->where('billingo_id', $created->id)->first();
                if ($document && isset($document['link'])) {
                    $response['link'] = '<p><a href="'
                        . esc_url($document['link'])
                        . '" id="wc_billingo_download" class="button button-primary" target="_blank">'
                        . __('Bizonylat megtekintése', 'billingo')
                        . '</a></p>';
                }
            }
        }

        wp_send_json_success($response);
    }

    /**
     * Extends order notification e-mail with document link
     *
     * @param WC_Order $order
     * @param boolean $sent_to_admin
     * @param string $plain_text
     * @param boolean $email
     */
    public static function action_woocommerce_email_before_order_table(
        WC_Order $order,
        bool     $sent_to_admin,
        string   $plain_text,
                 $email = false
    ): void
    {
        if (!$email) {

            return;
        }

        Billingo_Logger::info('action_woocommerce_email_before_order_table triggered for email ID: ' . $email->id);

        $repository = new Billingo_Repositroy();

        $text = '';
        $btn_text = '';
        $pdf_link = false;
        $email_id = $email->id;

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

        // invoice
        if (in_array(get_option('wc_billingo_email'), ['attach', 'both'])
            && in_array($email_id, [
                'customer_on_hold_order',
                'customer_completed_order',
                'customer_completed_renewal_order',
                'customer_completed_switch_order'
            ])) {
            Billingo_Logger::info('invoice email through woocommerce triggered');
            $pdf_link = $repository
                ->where('order_id', $order_id)
                ->where('type', TypeEnum::INVOICE->value)
                ->first();

            $pdf_link = $pdf_link ? $pdf_link['link'] : null;

            $text = get_option(
                'wc_billingo_email_woo_text',
                __('Számlája elkészült, melyet az alábbi linken tud megtekinteni.', 'billingo'));
            $btn_text = get_option('wc_billingo_email_woo_btn', __('Bizonylat megtekintése', 'billingo'));
        }
        // storno TODO: refactor this storno email attachment and generate storno if storno not exists (this whole if)
        //the solution is for problem: storno_document didnt exists in db at the time on the storno email sending
        //and the other problem in this part of the code the order status is 'processing', and the Invoice_Generation_Container dosent return with the cancellaction, so we need to generate it manually
        if (in_array(get_option('wc_billingo_storno_email'), ['attach', 'both'])
            && $email_id == 'customer_refunded_order'
            && get_option('wc_billingo_auto_storno') !== 'no') {
            Billingo_Logger::info('storno email through woocommerce triggered //storno email -before order table');

            // Check if storno document already exists
            $existing_storno = $repository
                ->where('order_id', $order_id)
                ->where('type', TypeEnum::CANCELLATION->value)
                ->first();

            // If storno doesn't exist yet, generate it immediately
            // For refunded order emails, we force generate storno regardless of order status
            if (!$existing_storno) {
                Billingo_Logger::info('Generating storno document for customer_refunded_order email, order: ' . $order_id);
                $invoice_generation_data = self::collect_invoice_generation_data($order_id);

                Billingo_Logger::startDocumentum();
                $billingo_document = $invoice_generation_data->getRepositroy()
                    ->where('type', TypeEnum::INVOICE->value)
                    ->where('order_id', $order_id)
                    ->first();

                if (!is_null($billingo_document)) {
                    $invoice_generation_data->getController()->cancelDocument($billingo_document['billingo_id']);
                }
                Billingo_Logger::endDocumentum();
            }

            $pdf_link = $repository
                ->where('order_id', $order_id)
                ->where('type', TypeEnum::CANCELLATION->value)
                ->first();

            $pdf_link = $pdf_link ? $pdf_link['link'] : null;

            $text = get_option('wc_billingo_storno_email_woo_text',
                __('Storno számlája elkészült, melyet az alábbi linken tud megtekinteni.', 'billingo'));
            $btn_text = get_option('wc_billingo_storno_email_woo_btn', __('Storno számla megtekintése','billingo'));
        }

        // proforma
        if (in_array(get_option('wc_billingo_proforma_email'), ['attach', 'both'])
            && in_array($email_id, ['customer_processing_order', 'customer_on_hold_order'])) {
            Billingo_Logger::info('proforma email through woocommerce triggered');
            $pdf_link = $repository
                ->where('order_id', $order_id)
                ->where('type', TypeEnum::PROFORMA->value)
                ->first();

            $pdf_link = $pdf_link ? $pdf_link['link'] : null;

            //if the proforma is not generated, we generate it with the should_proforma_generate function
            if (!$pdf_link) {
                self::should_proforma_generate($order_id);
                //sleep for 1 second waiting for the proforma to be generated, because this function is a void function and it does not return the document
                sleep(1);
                $pdf_link = $repository
                    ->where('order_id', $order_id)
                    ->where('type', TypeEnum::PROFORMA->value)
                    ->first();
                $pdf_link = $pdf_link ? $pdf_link['link'] : null;
            }

            $text = get_option('wc_billingo_proforma_email_woo_text',
                __('Díjbekérője elkészült, melyet az alábbi linken tud megtekinteni.', 'billingo'));
            $btn_text = get_option('wc_billingo_proforma_email_woo_btn', __('Díjbekérő megtekintése', 'billingo'));
        }

        // advance: az előlegszámla linkje a függőben lévő / feldolgozás alatti rendelés e-mailjében (az előlegszámla
        // a számlázási állapot elérésekor készül, ezért itt nincs külön legenerálás)
        if (in_array(get_option('wc_billingo_advance_email'), ['attach', 'both'])
            && in_array($email_id, ['customer_processing_order', 'customer_on_hold_order'])) {
            $advance_document = $repository
                ->where('order_id', $order_id)
                ->where('type', TypeEnum::ADVANCE->value)
                ->first();

            if ($advance_document) {
                Billingo_Logger::info('advance email through woocommerce triggered');
                $pdf_link = $advance_document['link'];
                $text = get_option('wc_billingo_advance_email_woo_text',
                    __('Előlegszámlája elkészült, melyet az alábbi linken tud megtekinteni.', 'billingo'));
                $btn_text = get_option('wc_billingo_advance_email_woo_btn', __('Előlegszámla megtekintése', 'billingo'));
            }
        }

        if (!$pdf_link) {
            return;
        }

        echo view('Admin.billingo_invoice_link_button', [
            'text' => $text,
            'btnText' => $btn_text,
            'pdfLink' => $pdf_link,
        ]);
    }

    private static function collect_invoice_generation_data(int $order_id): Invoice_Generation_Container
    {
        return new Invoice_Generation_Container($order_id);
    }

    private static function should_document_generate(Invoice_Generation_Container $invoice_generation_data): bool
    {
        return $invoice_generation_data->getStatus() === $invoice_generation_data->getActivationStatus();
    }

    /**
     * Megnézzük, hogy a rendelés fizetési módjához be van -e állítva a "Fizetés
     * ellenőrzés célállapota" (Fizetési módok beállítás) — az előlegszámla -> végszámla
     * automatizmus csak azoknál a fizetési módoknál fut le, amelyeknél ezt a boltos
     * kifejezetten beállította, a többi fizetési módnál az általános, egylépéses
     * generálási logika marad érvényben.
     */
    private static function get_payment_check_target_status(WC_Order $order): ?string
    {
        $targetStatus = get_option('wc_billingo_payment_check_status_' . $order->get_payment_method(), '');

        return empty($targetStatus) ? null : str_replace('wc-', '', $targetStatus);
    }

    /**
     * Megnézzük, hogy a rendelés éppen most érte -e el a számlázást aktiváló állapotot,
     * és a kapcsoló + a fizetési módjához beállított "Fizetés ellenőrzés célállapota"
     * miatt ilyenkor előlegszámlát kell -e kiállítani (a végszámla csak a fizetés-
     * ellenőrzés célállapotába kerüléskor készül el). Ha még nincs előlegszámla a
     * rendeléshez, azt most kell legenerálni.
     */
    private static function should_generate_advance_invoice_for_pipeline(Invoice_Generation_Container $invoice_generation_data): bool
    {
        if (!wcFlexibleIsTrue(get_option('wc_billingo_advance_invoice_linking_enabled'))) {
            return false;
        }

        if ($invoice_generation_data->getStatus() !== $invoice_generation_data->getActivationStatus()) {
            return false;
        }

        // Csak azoknál a fizetési módoknál fusson le az előlegszámla -> végszámla
        // automatizmus, amelyeknél a boltos beállította a fizetés-ellenőrzés célállapotát.
        if (self::get_payment_check_target_status($invoice_generation_data->getOrder()) === null) {
            return false;
        }

        $order_id = $invoice_generation_data->getOrder()->get_id();

        $existing_advance = $invoice_generation_data->getRepositroy()
            ->where('order_id', $order_id)
            ->where('type', TypeEnum::ADVANCE->value)
            ->first();

        return !$existing_advance;
    }

    /**
     * Megnézzük, hogy a végszámlát az előlegszámlához kell -e automatikusan
     * legenerálni az állapotváltás hatására: a kapcsoló be van kapcsolva, a rendelés
     * fizetési módjához be van állítva a fizetés-ellenőrzés célállapota, a rendelés
     * éppen ebbe az állapotba került, van hozzá érvényes (nem sztornózott)
     * előlegszámla, és még nincs a rendeléshez végszámla generálva.
     */
    private static function should_generate_final_invoice_from_advance(Invoice_Generation_Container $invoice_generation_data): bool
    {
        if (!wcFlexibleIsTrue(get_option('wc_billingo_advance_invoice_linking_enabled'))) {
            return false;
        }

        $targetStatus = self::get_payment_check_target_status($invoice_generation_data->getOrder());

        if ($targetStatus === null || $invoice_generation_data->getStatus() !== $targetStatus) {
            return false;
        }

        $order_id = $invoice_generation_data->getOrder()->get_id();

        $existing_invoice = $invoice_generation_data->getRepositroy()
            ->where('order_id', $order_id)
            ->where('type', TypeEnum::INVOICE->value)
            ->first();

        if ($existing_invoice) {
            return false;
        }

        $advance_invoice = $invoice_generation_data->getRepositroy()
            ->where('order_id', $order_id)
            ->where('type', TypeEnum::ADVANCE->value)
            ->first();

        return $advance_invoice && !empty($advance_invoice['billingo_id']);
    }

    private static function should_cancel_document(Invoice_Generation_Container $invoice_generation_data): bool
    {
        return $invoice_generation_data->getStatus() === $invoice_generation_data->getAutoStornoStatus();
    }
}
