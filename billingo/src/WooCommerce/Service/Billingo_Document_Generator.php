<?php

namespace App\Billingo\WooCommerce\Service;

use App\Billingo\Enums\Document\TypeEnum;
use App\Billingo\Enums\EntitlementEnum;
use App\Billingo\Enums\PaymentMethodEnum;
use App\Billingo\Enums\RoundEnum;
use App\Billingo\Enums\UnitPriceTypeEnum;
use App\Billingo\Enums\Document\LanguageEnum;
use App\Billingo\Enums\Partner\TaxTypeEnum;
use App\Billingo\Enums\VatEnum;
use App\Billingo\Exceptions\BadContentException;
use App\Billingo\Models\Document\DocumentInsert;
use App\Billingo\Models\Document\DocumentProductData;
use App\Billingo\Models\Partner\Partner;
use App\Billingo\Service\BillingoClient;
use Symfony\Component\HttpFoundation\Response;
use App\Billingo\WooCommerce\Service\Billingo_Product_Sync;
use App\Billingo\WooCommerce\Repositories\Billingo_Repositroy;
use App\Billingo\Enums\CurrencyEnum;
use App\Billingo\Api\CurrencyApi;
use WC_Order;
use WC_Order_Item;
use WC_Order_Refund;
use WC_Product;
use WC_Tax;

class Billingo_Document_Generator
{
    private readonly BillingoClient $client;
    private readonly WC_Order_Refund|WC_Order|bool $order;
    private array $documentData;
    private ?string $documentTypeCallback;
    private ?string $currentDocumentType = null;
    private bool $taxOverrideFailed = false;
    private ?string $lastError = null;
    private ?bool $vatExemptOrder = null;
    /** @var array{vat: string, entitlement: ?string}|null az adómentes rendelés tételeinek kódja (EU / kivitel / megjelölt vevő) */
    private ?array $orderExemptionCodes = null;
    private ?string $vatError = null;
    private ?string $stopError = null;
    private bool $skippedByDesign = false;
    private ?array $orderTaxRates = null;

    /** A rendelésen tárolt jelölés: a számlázás ehhez a rendeléshez ki van kapcsolva. */
    public const META_INVOICING_DISABLED = '_wc_billingo_invoicing_disabled';

    /** A szállítási díj számlán feltüntetett módjai (wc_billingo_shipping_price_mode). */
    public const SHIPPING_PRICE_GROSS = 'gross';
    public const SHIPPING_PRICE_NET = 'net';

    /**
     * Ki van-e kapcsolva a számlázás az adott rendelésre (a rendelés oldalán kapcsolható).
     * Az automatikus és a kézi bizonylatkészítést is megakadályozza; a sztornózás nem érintett.
     */
    public static function isInvoicingDisabledForOrder(mixed $order): bool
    {
        if (is_numeric($order)) {
            $order = wc_get_order((int)$order);
        }

        return is_object($order)
            && method_exists($order, 'get_meta')
            && wcFlexibleIsTrue($order->get_meta(self::META_INVOICING_DISABLED, true));
    }

    public static function setInvoicingDisabledForOrder(WC_Order $order, bool $disabled): void
    {
        if ($disabled) {
            $order->update_meta_data(self::META_INVOICING_DISABLED, 'yes');
        } else {
            $order->delete_meta_data(self::META_INVOICING_DISABLED);
        }

        $order->save();
    }

    public function __construct(int $orderId, private readonly ?array $manualIncome = null)
    {
        $this->client = new BillingoClient(get_option('wc_billingo_api_key', ''));
        $this->order = wc_get_order($orderId);

        if (!($this->order instanceof WC_Order)) {
            $this->lastError = __('A rendelés nem található vagy nem számlázható.', 'billingo');
            $this->documentTypeCallback = null;

            return;
        }

        $this->documentTypeCallback = $this->selectCallback();
    }

    /**
     * A generálás sikertelensége esetén a tényleges okot adja vissza (ember által
     * olvasható formában), hogy a manuális generálás felülete ne csak egy általános
     * "sikertelen generálás" üzenetet mutasson.
     */
    /**
     * Igaz, ha a bizonylat elmaradása szándékos beállításból következik (kikapcsolt számlázás a rendelésen vagy a
     * fizetési módon, alrendelés, 0 Ft-os rendelés) — ilyenkor nincs mit jelezni a boltosnak hibaként.
     */
    public function wasSkippedByDesign(): bool
    {
        return $this->skippedByDesign;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function get(): ?DocumentInsert
    {
        if (!($this->order instanceof WC_Order)) {
            return null; // a lastError-t a konstruktor már beállította
        }

        $this->skippedByDesign = false;

        if ($this->isForbidden()) {

            return null;
        }

        $callback = $this->documentTypeCallback;

        if (is_null($callback)) {
            $this->lastError = __('Nincs érvényes bizonylattípus kiválasztva a beállításokban (számla/díjbekérő/piszkozat).', 'billingo');
            // A „Nem” (kikapcsolt automatikus számlázás) szándékos beállítás: ilyenkor a rendelésre nem kerül
            // „a bizonylat nem készült el” megjegyzés (minden rendelés megkapta volna).
            $this->skippedByDesign = true;

            return null;
        }

        return $this->$callback();
    }

    public function getInvoice(): ?DocumentInsert
    {
        $invoice = $this->make(TypeEnum::INVOICE);

        // A make() null-t ad (pl. kikapcsolt számlázás, hibás ÁFA), ilyenkor nincs mit kiegészíteni.
        if ($invoice === null) {
            return null;
        }

        $paymentMethode = $this->order->get_payment_method() ?? get_option('wc_billingo_fallback_payment');
        $paid = (bool)get_option("wc_billingo_mark_as_paid_{$paymentMethode}");

        if ($paid){
            $invoice->paid = true;
        }

        return $invoice;
    }

    public function getProforma(): ?DocumentInsert
    {
        $proforma = $this->make(TypeEnum::PROFORMA);

        if ($proforma === null) {
            return null;
        }

        $paymentMethode = $this->order->get_payment_method() ?? get_option('wc_billingo_fallback_payment');
        $paid = (bool)get_option("wc_billingo_mark_as_paid2_{$paymentMethode}");

        if ($paid){
            $proforma->paid = true;
        }

        return $proforma;
    }

    public function getDraft(): ?DocumentInsert
    {
        return $this->make(TypeEnum::DRAFT);
    }

    public function getAdvance(): ?DocumentInsert
    {
        return $this->make(TypeEnum::ADVANCE);
    }

    private function make(TypeEnum $type): ?DocumentInsert
    {
        // A wc_get_order() hamis értéket ad törölt/érvénytelen azonosítóra (vagy visszatérítés-objektumot ad):
        // fatal error helyett érthető hibával állunk meg.
        if (!($this->order instanceof WC_Order)) {
            $this->lastError = __('A rendelés nem található vagy nem számlázható.', 'billingo');
            Billingo_Logger::error('A bizonylat nem készíthető: a rendelés nem található vagy érvénytelen.');

            return null;
        }

        Billingo_Logger::info('Order ID: ' . $this->order->get_id());

        $this->currentDocumentType = $type->value;

        // Ugyanazon példányon újabb bizonylat készítésekor az előző kísérlet hibája nem maradhat meg.
        $this->vatError = null;
        $this->stopError = null;
        $this->skippedByDesign = false;
        $this->taxOverrideFailed = false;
        $this->vatExemptOrder = null;

        // A rendelés oldalán kikapcsolt számlázás: a bizonylat-adatok (és a partner Billingo-beli
        // létrehozása) összeállítása előtt megállunk, így semmilyen úton nem készülhet bizonylat.
        if (self::isInvoicingDisabledForOrder($this->order)) {
            $this->lastError = __('Ehhez a rendeléshez a számlázás ki van kapcsolva (a rendelés oldalán visszakapcsolható).', 'billingo');
            $this->skippedByDesign = true;
            Billingo_Logger::info('Számlázás kihagyva: a rendeléshez ki van kapcsolva a számlázás. Rendelés ID: ' . $this->order->get_id());

            return null;
        }

        $this->collectDocumentData();

        if ($this->taxOverrideFailed) {
            $this->lastError = __('Az ÁFA felülírás be van kapcsolva, de nincs érvényes ÁFA érték kiválasztva a beállításoknál.', 'billingo');
            Billingo_Logger::error(ucfirst($type->value) . " " . $this->order->get_id() . " számú rendelés esetében a számlázás megszakítva: " . $this->lastError);

            add_action('admin_notices', function () {
                echo '<div class="error notice"><p>' . esc_html__('A Billingo áfa felülírás be van kapcsolva, de nincs érvényes áfa érték kiválasztva a beállításoknál. A számla/díjbekérő generálása emiatt megszakadt.', 'billingo') . '</p></div>';
            });

            return null;
        }

        // A WooCommerce által felszámított áfakulcs nem azonosítható a Billingo kulcsai között:
        // hibás számla helyett a generálás megáll, és a rendelésen megjegyzés jelzi az okot.
        // Az ÁFA-hiba nem számít, ha az érintett tételek kódját úgyis felülírja az áfamentes EU-s rendelés
        // (minden sort EU/KBAET-re állít) vagy az ÁFA felülírás a termékekre és a szállításra is érvényes.
        $vatErrorIsBypassed = $this->vatError !== null
            && ($this->isVatExemptOrder() || $this->shouldOverrideCarrierTax());

        $stopMessage = $this->stopError ?? ($vatErrorIsBypassed ? null : $this->vatError);

        if ($stopMessage !== null) {
            $this->lastError = $stopMessage;
            Billingo_Logger::error(ucfirst($type->value) . " " . $this->order->get_id() . " számú rendelés esetében a számlázás megszakítva: " . $stopMessage);
            $this->order->add_order_note(__('Billingo: a bizonylat nem készült el. ', 'billingo') . $stopMessage);

            return null;
        }

        $this->documentData['type'] = $type->value;
        if($type->value != 'invoice'){
            unset($this->documentData['vendor_id']);
        }
        if($this->documentData['skipcreatedocuments'] == 0){
            $document = new DocumentInsert($this->documentData);
        } else {
            $this->lastError = __('A számlázás ki van kapcsolva ehhez a fizetési módhoz a beállításokban.', 'billingo');
            $this->skippedByDesign = true;
            Billingo_Logger::error(ucfirst($type->value) . " " .  $this->order->get_id() . " számú rendelés esetében a számlázás kikapcsolva.");
            return null;
        }

        if ($document->hasError()) {

            $errors = $document->getErrors();
            $detailedMessage = $this->flattenErrorMessage($document->getSelfTest()->getErrors() ?? []);
            $this->lastError = $detailedMessage !== ''
                ? $detailedMessage
                : __('A rendelés/bizonylat adatai hiányosak vagy érvénytelenek.', 'billingo');
            // A getErrors() csak a dokumentum saját hibáját adja; beágyazott hibánál (pl. settings) null.
            Billingo_Logger::error(ucfirst($type->value) . " generation: FAIL " . ($errors?->getType()?->value ?? 'nested')
                . ' '
                . (empty($errors?->getValues()) ? '' : json_encode($errors->getValues()))
                . ' | Részletes hiba: ' . $this->lastError);
            return null;
        } else {

            Billingo_Logger::info(ucfirst($type->value) . ' generation: SUCCESSFUL');
        }

        if($document && $this->shouldSendEmail($document->type)){

            $document->settings->should_send_email = true;
            Billingo_Logger::info('Email send by Billingo');
        }
        Billingo_Logger::info('Document: ' . json_encode($document));
        return $document;
    }

    /**
     * A getSelfTest()->getErrors() beágyazott, tetszőleges mélységű tömböt ad vissza,
     * aminek levelei BillingoError objektumok — ez egy rövid, ember által olvasható
     * szöveggé alakítja, hogy a manuális generálás felülete a tényleges hibaokot tudja
     * megjeleníteni az általános "sikertelen generálás" üzenet helyett.
     */
    private function flattenErrorMessage(array $errors, string $prefix = ''): string
    {
        $messages = [];

        foreach ($errors as $key => $value) {
            $path = $prefix === '' ? (string)$key : $prefix . '.' . $key;

            if ($value instanceof \App\Billingo\Error\BillingoError) {
                $fieldMessages = $value->getValues();

                if (!empty($fieldMessages)) {
                    foreach ($fieldMessages as $field => $fieldErrors) {
                        $messages[] = $path . '.' . $field . ': ' . implode(', ', (array)$fieldErrors);
                    }
                } else {
                    $messages[] = $path . ': ' . $value->getType()->value;
                }
            } elseif (is_array($value)) {
                $nested = $this->flattenErrorMessage($value, $path);

                if ($nested !== '') {
                    $messages[] = $nested;
                }
            }
        }

        return implode(' | ', $messages);
    }

    private function collectDocumentData(): void
    {
        $paymentMethod = $this->resolvePaymentMethod();
        $paidType = $this->resolvePaidType();
        Billingo_Logger::info('Payment Currency: ' . $this->order->get_currency());

        $deadline = isset($this->manualIncome['deadline'])
            ? (int)$this->manualIncome['deadline']
            : (int)get_option("wc_billingo_paymentdue_{$this->order->get_payment_method()}");
        $language = $this->get_order_language($this->order);

        $currency = $this->order->get_currency() ?: 'HUF';
        $countryCode = $this->order->get_billing_country();

        // Bankszámla ID meghatározása deviza alapján
        $bankAccountId = $this->order->get_currency() === 'EUR'
            ? get_option('wc_billingo_bank_account_eur')
            : get_option('wc_billingo_bank_account_huf');

        // Ha üres string vagy nem numerikus, akkor nullra állítjuk
        if (empty($bankAccountId) || !is_numeric($bankAccountId)) {
            $bankAccountId = null;
        } else {
            $bankAccountId = (int)$bankAccountId;
        }

        Billingo_Logger::info('Bank account ID: ' . ($bankAccountId ?: 'not set (using default)') . ' Currency: ' . $this->order->get_currency() );
        // Ha ehhez a rendeléshez már van korábban kiállított, érvényes (nem sztornózott) előlegszámla, és most a
        // végleges számlát (invoice) állítjuk ki, a végszámla az előleget rendezi — és ugyanarra a partnerre kell
        // szólnia, mint az előlegszámla. A partner név szerinti keresése nem megbízható: a Billingo az újonnan
        // létrehozott partnert másodpercekig nem listázza, ilyenkor új, duplikált partner jönne létre, és a Billingo
        // elutasítaná az előleg felhasználását (gyors előleg → végszámla folyamatnál mindig).
        $advanceInvoiceId = null;
        $advancePartnerId = null;

        if ($this->currentDocumentType === TypeEnum::INVOICE->value && wcFlexibleIsTrue(get_option('wc_billingo_advance_invoice_linking_enabled'))) {
            $advanceInvoiceId = $this->findActiveAdvanceInvoiceId();

            if ($advanceInvoiceId !== null) {
                $advancePartnerId = $this->getAdvancePartnerId($advanceInvoiceId);
            }
        }

        $document = [
            'skipcreatedocuments' => get_option("wc_billingo_doff_{$this->order->get_payment_method()}",0),
            'vendor_id'=> $this->buildVendorId(),
            'partner_id' => $advancePartnerId ?? $this->findOrCreatePartner($this->getPartnerName()),
            'block_id' => (int)get_option('wc_billingo_invoice_block'),
            'bank_account_id' => $bankAccountId,
            'fulfillment_date' => isset($this->manualIncome['completed'])
                ? $this->manualIncome['completed']
                : wp_date('Y-m-d', time()),
            'due_date' => wp_date('Y-m-d', strtotime('+' . $deadline . ' days')),
            'payment_method' => $paymentMethod,
            'paid' => $paidType,
            'language' => $language,
            'currency' => $currency,
            'conversion_rate' => $currency === 'HUF' ? 1.0 : $this->resolveConversionRate($currency),
            'electronic' => wcFlexibleIsTrue(get_option('wc_billingo_electronic')),
            'items' => $this->applyOrderVatExemption($this->createProductItems($countryCode)),
            'comment' => $this->getNote($this->order),
            'settings' => [
                'round' => $this->getInvoiceRounding($currency),
                'without_financial_fulfillment' => wcFlexibleIsTrue(get_option('mark_paid_without_financial_fulfillment')) && $paidType,
                'should_send_email' => false,
            ],
        ];

        // „Közvetített szolgáltatást tartalmaz” jelölőnégyzet: bekapcsolt beállításnál csak akkor
        // jelöljük be, ha a számlán ténylegesen pozitív összegű szállítási díj szerepel, ingyenes
        // szállításnál jelöletlen marad. Kikapcsolt beállításnál a mezőt nem küldjük.
        if (wcFlexibleIsTrue(get_option('wc_billingo_mediated_service_by_shipping'))) {
            $document['settings']['mediated_service'] = $this->hasPositiveShippingCost();
        }

        // Ha ehhez a rendeléshez már van korábban kiállított, érvényes (nem sztornózott)
        // előlegszámla, és most a végleges számlát (invoice) állítjuk ki, jelezzük a
        // Billingo felé, hogy ez a számla az előleget rendezi — a Billingo automatikusan
        // levonja az előleg összegét a végszámla végösszegéből.
        if ($advanceInvoiceId !== null) {
            $document['advance_invoice'] = [$advanceInvoiceId];
            Billingo_Logger::info('Végszámla generálása előlegszámla alapján. Előlegszámla Billingo ID: ' . $advanceInvoiceId);
        }

        $this->documentData = $document;
    }

    /**
     * Az előlegszámla Billingo-beli partnerének azonosítója (a végszámla ugyanerre a partnerre szól). Ha a lekérdezés
     * nem sikerül, null: ilyenkor a megszokott partner-keresés / létrehozás dolgozik.
     */
    private function getAdvancePartnerId(int $advanceInvoiceId): ?int
    {
        try {
            $response = $this->client->document()->getById($advanceInvoiceId)->getResponse();

            if ($response->getStatusCode() === Response::HTTP_OK) {
                $partnerId = $response->getData()->toArray()['partner']['id'] ?? null;

                if (!empty($partnerId)) {
                    Billingo_Logger::info('Végszámla partnere az előlegszámláról átvéve. Partner ID: ' . $partnerId);

                    return (int)$partnerId;
                }
            }
        } catch (\Throwable $e) {
            Billingo_Logger::warning('Az előlegszámla partnere nem kérdezhető le: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Megkeresi a rendeléshez tartozó, érvényes (nem sztornózott) előlegszámla Billingo
     * ID-ját, ha van ilyen.
     */
    private function findActiveAdvanceInvoiceId(): ?int
    {
        $row = (new Billingo_Repositroy())
            ->where('order_id', $this->order->get_id())
            ->where('type', TypeEnum::ADVANCE->value)
            ->first();

        return ($row && !empty($row['billingo_id'])) ? (int)$row['billingo_id'] : null;
    }

    /**
     * A Billingo a vendor_id alapján szűri a duplikált beküldéseket a saját oldalán. Ha ez
     * mindig ugyanaz maradna (pl. csak a rendelés ID), akkor egy sztornózott számla utáni
     * új, jogos számla kiállítása is duplikátumnak tűnne az API számára és elutasításra kerülne
     * — miközben a plugin saját DB-ellenőrzése (Billingo_Controller::createDocument()) már
     * helyesen engedélyezné az új bizonylatot. Ezért a vendor_id-t a rendeléshez tartozó,
     * ugyanolyan típusú, korábban ténylegesen létrehozott (akár sztornózott) bizonylatok
     * számával tesszük egyedivé, típusonként külön sorszámozva.
     *
     * Ha ugyanahhoz az állapothoz (még egyetlen bizonylat sem jött létre) két kérés fut be
     * egymás után (pl. verseny­helyzet), a vendor_id ugyanaz marad, így a Billingo oldali
     * duplikátumszűrés ebben az esetben továbbra is védelmet nyújt.
     */
    private function buildVendorId(): string
    {
        $orderId = $this->order->get_id();
        $type = $this->currentDocumentType ?? 'document';

        $previousCount = count(
            (new Billingo_Repositroy())
                ->withCanceled()
                ->where('order_id', $orderId)
                ->where('type', $type)
                ->get()
        );

        // Multisite támogatás: ha ugyanaz a Billingo fiók több különálló webshophoz van
        // kötve, a rendelés ID-k ütközhetnek (pl. mindkét oldalon lehet #100-as rendelés),
        // ami a vendor_id-t is ütköztetné a Billingo oldalán — annak ellenére, hogy két
        // teljesen független rendelésről van szó. Ha be van kapcsolva, egy admin által
        // megadott, webshoponként egyedi azonosítót teszünk a vendor_id elejére.
        $sitePrefix = '';
        if (wcFlexibleIsTrue(get_option('wc_billingo_multisite_enabled'))) {
            $siteIdentifier = trim((string)get_option('wc_billingo_multisite_identifier', ''));

            if ($siteIdentifier !== '') {
                $sitePrefix = $siteIdentifier . '-';
            }
        }

        return $sitePrefix . $orderId . '-' . $type . '-' . ($previousCount + 1);
    }

    private function resolvePaidType(): bool
    {
        $paymentMethodName = $this->order->get_payment_method() ?? get_option('wc_billingo_fallback_payment');

        // Első ellenőrizzük, hogy van-e specifikus beállítás erre a fizetési módszerre
        // A currentDocumentType alapján választjuk ki a megfelelő beállítást
        if ($this->currentDocumentType === TypeEnum::PROFORMA->value) {
            // Díjbekérő esetén a mark_as_paid2 beállítást használjuk
            $paidSetting = get_option("wc_billingo_mark_as_paid2_{$paymentMethodName}");
        } else {
            // Éles számla esetén a mark_as_paid beállítást használjuk
            $paidSetting = get_option("wc_billingo_mark_as_paid_{$paymentMethodName}");
        }

        if (wcFlexibleIsTrue($paidSetting)) {
            return true;
        }

        //if not paid, then return false
        return false;
    }

    private function resolvePaymentMethod(): string
    {
        Billingo_Logger::info('Payment method original by woocommerce: ' . $this->order->get_payment_method());

        // Első prioritás: Admin felületen beállított egyedi leképezés
        $adminPaymentMethod = get_option('wc_billingo_payment_method_' . $this->order->get_payment_method());

        // Csak érvényes Billingo fizetési mód fogadható el: a korábbi felület a „-- Válassz! --” feliratot mentette el
        // értékként, ha egy fizetési módhoz nem volt hozzárendelés — az érvénytelen érték a Billingo elutasítását
        // (a számlakészítés leállását) okozta ahelyett, hogy az automatikus leképezés / tartalék érték érvényesült volna.
        if (!empty($adminPaymentMethod) && PaymentMethodEnum::tryFrom((string)$adminPaymentMethod) === null) {
            Billingo_Logger::info('Payment method from admin settings is invalid, ignored: ' . $adminPaymentMethod);
            $adminPaymentMethod = '';
        }

        if (!empty($adminPaymentMethod)) {
            $paymentMethod = $adminPaymentMethod;
            Billingo_Logger::info('Payment method from admin settings: ' . $paymentMethod);
        } else {
            // Második prioritás: Automatikus leképezés a fromWoocommerce függvényből
            $paymentMethod = PaymentMethodEnum::fromWoocommerce($this->order->get_payment_method())?->value;

            if ($paymentMethod) {
                Billingo_Logger::info('Payment method from automatic mapping: ' . $paymentMethod);
            } else {
                // Harmadik prioritás: Fallback érték
                $paymentMethod = get_option('wc_billingo_fallback_payment');
                Billingo_Logger::info('Payment method from fallback: ' . $paymentMethod);
            }
        }

        Billingo_Logger::info('Payment method to billingo compiled: ' . $paymentMethod);
        return $paymentMethod;
    }

    private function getPartnerName(): string
    {
        $partnerName = wcFlexibleIsTrue(get_option('wc_billingo_flip_name'))
            ? "{$this->order->get_billing_first_name()} {$this->order->get_billing_last_name()}"
            : "{$this->order->get_billing_last_name()} {$this->order->get_billing_first_name()}";

        if (!empty($this->order->get_billing_company())) {
            $partnerName = get_option('wc_billingo_company_name')
                ? $this->order->get_billing_company()
                : "{$this->order->get_billing_company()} {$partnerName}";
        }

        return $partnerName;
    }

    private function findOrCreatePartner(string $name): ?int
    {
        // Az adószám forrása: a beállított egyedi meta mező, ennek hiányában a pénztár saját adószám mezője
        $vatNumber = $this->getOrderVatNumber();
        Billingo_Logger::info('VAT number: ' . ($vatNumber ?? ''));

        $address1 = $this->order->get_billing_address_1();

        if (strlen(trim($address1)) < 2 && wcFlexibleIsTrue(get_option('wc_billingo_test', false))) {
            // ha üres vagy rövidebb mint 1 karakter, egészítsük ki
            $address1 = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 2);
        }
        $descriptions = [
            'name' => $name,
            'address' => [
                'country_code' => $this->order->get_billing_country() ?: 'HU',
                'post_code' => $this->order->get_billing_postcode(),
                'city' => $this->order->get_billing_city(),
                'address' => $address1,
            ],
        ];
        // Add VAT number to partner data if available
        if (!empty($vatNumber)) {
            $descriptions['taxcode'] = $vatNumber;
            // Nem magyar országkódú partnernél a Billingo API kötelezően FOREIGN típust vár
            // ("the customer type must be set to 'Foreign'"), a HAS_TAX_NUMBER csak magyar
            // adószámhoz használható.
            $descriptions['tax_type'] = $this->getPartnerTaxType((string)$descriptions['address']['country_code']);
            Billingo_Logger::info("VAT number found for partner: {$name}");
        }

        $foundPartner = $this->findPartner($descriptions);

        if (!is_null($foundPartner)) {

            return $foundPartner->id;
        }

        $descriptions['emails'] = [$this->order->get_billing_email()];

        // Adószám nélküli partner (magánszemély): a Billingo "Partner adózási típusa" mezője ne maradjon üresen, mert a
        // piszkozaton egyik lehetőség (magánszemély / adószámmal rendelkezik / külföldi) sem jelölődne ki. A típus csak
        // létrehozáskor kerül be, a keresésnél nem (a már meglévő, típus nélküli partnereket így továbbra is megtaláljuk).
        if (empty($descriptions['tax_type'])) {
            $descriptions['tax_type'] = $this->getPartnerTaxTypeWithoutTaxNumber((string)$descriptions['address']['country_code']);
        }

        try {
            $createdPartner = $this->client
                ->partner()
                ->create($descriptions)
                ->getResponse();

            if ($createdPartner->getStatusCode() === Response::HTTP_CREATED) {

                $createdPartnerId = $createdPartner->getData()->id;

                Billingo_Logger::info("Partner created with ID: {$createdPartnerId}" . (!empty($vatNumber) ? " and VAT number: {$vatNumber}" : ""));
            } else {

                Billingo_Logger::error('Partner created FAILED: ' . json_encode($createdPartner->getErrors()));
                $createdPartnerId = null;
            }

        } catch (BadContentException $e) {

            Billingo_Logger::error('Partner created FAILED: ' . json_encode($e->getErrors()));
            $createdPartnerId = null;
        }

        return $createdPartnerId;
    }

    /**
     * Adószámos partner Billingo adótípusa: magyar adószámhoz HAS_TAX_NUMBER, nem magyar
     * országkódnál a Billingo API kötelezően FOREIGN típust vár.
     */
    private function getPartnerTaxType(string $countryCode): string
    {
        return strtoupper(trim($countryCode)) !== 'HU'
            ? TaxTypeEnum::FOREIGN->value
            : TaxTypeEnum::HAS_TAX_NUMBER->value;
    }

    /**
     * Adószám nélküli partner adótípusa: magyar magánszemély "Adószámmal nem rendelkezik (természetes személy)",
     * külföldi esetén "Külföldi".
     */
    private function getPartnerTaxTypeWithoutTaxNumber(string $countryCode): string
    {
        return strtoupper(trim($countryCode)) !== 'HU'
            ? TaxTypeEnum::FOREIGN->value
            : TaxTypeEnum::NO_TAX_NUMBER->value;
    }

    private function findPartner(array $descriptions): ?Partner
    {
        $foundPartner = null;
        $this->client
            ->partner()
            ->query()
            ->whereQuery($descriptions['name'])
            ->getData()
            ->each(function ($partner) use ($descriptions, &$foundPartner) {

                if (isArraySubset($descriptions, $partner->toArray())) {
                    $foundPartner = $partner;
                }
            });

        if (!is_null($foundPartner)) {

            foreach ($foundPartner->emails as $email) {
                if ($this->order->get_billing_email() === $email) {

                    Billingo_Logger::info("Partner found with ID: {$foundPartner->id}");

                    return $foundPartner;
                }
            }
        }

        Billingo_Logger::info("Partner NOT FOUND");

        return null;
    }

    /**
     * Ellenőrzi, hogy a tételenkénti kedvezmény engedélyezve van-e
     *
     * @return bool
     */
    private function isItemDiscountEnabled(): bool
    {
        return wcFlexibleIsTrue(get_option('wc_billingo_discount_is_unique_item', false));
    }

    /**
     * Ellenőrzi és hozzáadja a tételenkénti kedvezményeket a termékekhez.
     *
     * @param DocumentProductData[] $productItems Termék tételek tömbje
     * @param WC_Order_Item $item A WooCommerce rendelési tétel
     * @param array $itemData A tétel adatai
     * @param WC_Product|null $product A termék objektum
     * @param bool $isBundleItem Igaz, ha bundle részterméke
     * @return DocumentProductData[] A frissített termék tételek tömbje
     */
    private function applyItemDiscounts(array $productItems, $item, array $itemData, $product, bool $isBundleItem = false): array
    {
        // Ha a tételenkénti kedvezmény beállítás nincs bekapcsolva, csak visszaadjuk az eredeti tömböt
        if (!$this->isItemDiscountEnabled()) {
            return $productItems;
        }
        // Bundle elemekre ne alkalmazzunk tételenkénti kedvezményt
        if ($isBundleItem) {
            return $productItems;
        }

        Billingo_Logger::info('Termék kedvezmény ellenőrzés');
        Billingo_Logger::info('Item name: ' . $itemData['name']);
        Billingo_Logger::info('Regular price: ' . $this->getRegularPriceFromItemMeta($item, $product) . ', Sale price: ' . $this->getSalePriceFromItemMeta($item));
        Billingo_Logger::info('Item total (WC already includes discounts): ' . $item->get_total());


        $priceDiff = $this->getSaleDiscountPerUnit($item, $itemData);

        // Fillérnyi kerekítési eltérés (pl. 38,0619 a tárolt 38,06 ellen) nem lehet "kedvezmény".
        if ($priceDiff > 0) {

            $Discount = $this->roundsToWholeAmounts()
                ? (int) round($priceDiff)
                : $priceDiff;


            Billingo_Logger::info('Kedvezmény tétel hozzáadása bruttó értékkel: ' . $Discount);

            // Kedvezmény tétel hozzáadása bruttó értékkel
            // Az adómentes tételek kedvezménysorának is kell jogcím, mint a termék sorának (különben a Billingo elutasítja).
            $discountEntitlement = $this->getCalculatedDateForItem('entitlement', $itemData)?->value;

            $discountItem = new DocumentProductData(array_filter([
                'name' => __('Kedvezmény - ', 'billingo') . ($itemData['name'] ?? 'Termék'),
                'quantity' => $itemData['quantity'] ?? 1,
                'unit_price' => -$Discount,
                'unit_price_type' => UnitPriceTypeEnum::GROSS->value,
                'unit' => $this->getCalculatedDateForItem('unit'),
                'vat' => $this->getCalculatedDateForItem('vat', $itemData)->value ?? '0%',
                'entitlement' => $discountEntitlement,
            ], static fn($value) => $value !== null));

            // áfa felülírás ha kell
            if (wcFlexibleIsTrue(get_option('wc_billingo_tax_override'))) {
                $discountItem = $this->overrideTax($discountItem);
            }
            $discountItem = $this->applySkuVat($discountItem, $itemData);

            $productItems[] = $discountItem;
        }

        return $productItems;
    }

    /**
     * A termék akciós kedvezménye egy darabra (bruttó): az eltárolt normál ár (akció nélkül) és a ténylegesen fizetett
     * egységár különbsége; 0, ha a "Kedvezmény külön tételként" kikapcsolt, nincs eltárolt ár, vagy nincs akció.
     * A bruttósításhoz a számlára kerülő (felülírás utáni) áfakulcsot használjuk.
     */
    private function getSaleDiscountPerUnit($item, array $itemData): float
    {
        if (!$this->isItemDiscountEnabled()) {
            return 0.0;
        }

        // Ugyanaz a feltétel, mint a "Kedvezmény" sor készítésénél: csak mentett akciós árnál.
        if (empty(wc_get_order_item_meta($item->get_id(), '_wc_billingo_product_full_price_with_sale', true))) {
            return 0.0;
        }

        $vatObject = $this->getCalculatedDateForItem('vat', $itemData)?->value ?? VatEnum::PERCENT_0->value;
        $vatRate = $this->getVatRateFromCode($this->getFinalFeeVatCode($vatObject, false, false));

        $regularPrice = $this->getRegularPriceFromItemMeta($item, null);
        if (!wcFlexibleIsTrue(get_option('woocommerce_prices_include_tax'))) {
            $regularPrice *= (1 + ($vatRate / 100));
        }

        $salePrice = $this->getSalePriceFromItemMeta($item);

        // Nullás termékek (pl. ajándékok) kezelése
        if ($item->get_total() == 0) {
            $meta = $item->get_meta_data();
            if (isset($meta['type']) && stripos($meta['type'], 'free')) {
                $salePrice = 0;
            }
        }

        Billingo_Logger::info('Akciós kedvezmény számítás - normál ár: ' . $regularPrice . ', fizetett (akciós) ár: ' . $salePrice);

        // Fillérnyi kerekítési eltérés (pl. 38,0619 a tárolt 38,06 ellen) nem lehet "kedvezmény".
        return round($regularPrice - $salePrice, 2) > 0 ? $regularPrice - $salePrice : 0.0;
    }

    /**
     * Alkalmazza a kupon kedvezményeket, ha engedélyezve van.
     *
     * @param DocumentProductData[] $productItems Termék tételek tömbje
     * @param array $items Rendelési tételek tömbje
     * @return DocumentProductData[] A frissített termék tételek tömbje
     */
    private function applyCouponDiscounts(array $productItems, array $items): array
    {

        // Kupon kedvezmény hozzáadása - minden kuponhoz külön tétel
        $usedCoupons = $this->order->get_coupon_codes();
        Billingo_Logger::info('Used coupons: ' . json_encode($usedCoupons));

        if (!empty($usedCoupons)) {
            // Minden egyes kuponhoz külön tételt hozunk létre
            foreach ($usedCoupons as $couponCode) {
                // Lekérjük a kupon kedvezmény összegét
                $couponDiscount = 0;
                $couponDiscountTax = 0.0;

                // Megkeressük a kupon adatait a rendelés tételei között
                foreach ($this->order->get_items('coupon') as $couponItem) {
                    if ($couponItem->get_code() === $couponCode) {
                        $couponDiscount = abs(floatval($couponItem->get_discount()));
                        $couponDiscountTax = method_exists($couponItem, 'get_discount_tax')
                            ? abs(floatval($couponItem->get_discount_tax()))
                            : 0.0;
                        Billingo_Logger::info("Kupon '{$couponCode}' nyers kedvezmény: {$couponDiscount}");
                        break;
                    }
                }



                if ($couponDiscount > 0) {
                    // Ellenőrizzük, hogy szállítási kuponról van-e szó
                    $isShippingCoupon = $this->isCouponForShipping($couponCode);

                    if ($isShippingCoupon && $this->isCouponDiscountAlreadyOnItems()) {
                        // A WooCommerce a kupon kedvezményét a termék-tételekre számolta (a "- kedvezmény" sorok a
                        // termékeknél már szerepelnek): a külön szállítási kupon sor ugyanazt még egyszer levonná.
                        Billingo_Logger::info("Kupon '{$couponCode}': a kedvezmény már szerepel a termék-tételeknél, külön sor kimarad");

                        continue;
                    }

                    if ($isShippingCoupon) {
                        // Szállítási kupon esetén a szállítási ÁFA kulcsot használjuk
                        $couponVat = $this->getShippingCouponVatData();
                        $vatCode = $couponVat['vat'] ?? VatEnum::PERCENT_0->value;
                        // A WooCommerce a kedvezményt is adó nélkül tárolja: bruttó módban a rögzített kedvezmény-áfát
                        // hozzáadjuk (a szállítási sorral azonos módon), nettó módban nettó tételként küldjük.
                        $couponPriceType = $this->getShippingPriceMode() === self::SHIPPING_PRICE_NET
                            ? UnitPriceTypeEnum::NET
                            : UnitPriceTypeEnum::GROSS;
                        if ($couponPriceType === UnitPriceTypeEnum::GROSS) {
                            $couponDiscount += $couponDiscountTax;
                        }

                        Billingo_Logger::info("Szállítási kupon ÁFA kulcsa: {$vatCode}");


                        $discountItem = new DocumentProductData(array_filter([
                            'name' => __('Kupon kedvezmény', 'billingo'),
                            'quantity' => 1,
                            'unit_price' => -($this->roundsToWholeAmounts()
                                ? (int)round($couponDiscount)
                                : $couponDiscount),
                            'unit_price_type' => $couponPriceType->value,
                            'unit' => $this->getCalculatedDateForItem('unit'),
                            'vat' => $vatCode,
                            'entitlement' => $couponVat['entitlement'] ?? null,
                        ], static fn($value) => $value !== null));
                        // Áfa felülírás szállítási kupon esetén

                        // Az ÁFA felülírás a kedvezmény sorra akkor is érvényes, ha a szállításra nem (a kedvezmény
                        // nem szállítási tétel); a "szállításra is" kapcsoló önmagában, kikapcsolt felülírás mellett
                        // semmit nem írhat felül.
                        if (wcFlexibleIsTrue(get_option('wc_billingo_tax_override'))) {
                            $discountItem = $this->overrideTax($discountItem);
                        }
                        $productItems[] = $discountItem;
                        Billingo_Logger::info("Kupon kedvezmény tétel hozzáadva: {$couponCode} (-{$couponDiscount} {$this->order->get_currency()}) " . ($isShippingCoupon ? "[SZÁLLÍTÁSI KUPON]" : "[TERMÉK KUPON]"));

                    }
                }
            }
        }

        return $productItems;
    }

    /**
     * Létrehozza a termék elemeket a számla/díjbekérő dokumentumhoz.
     *
     * @return array A DocumentProductData elemek tömbje
     */
    private function createProductItems( ?string $countryCode = null): array
    {
        $items = $this->order->get_items();
        $productItems = [];
        // check for should sync products to billingo products
        $shouldSyncProducts = (bool)get_option('wc_billingo_product_sync', false) &&
            $this->currentDocumentType === TypeEnum::INVOICE->value;

        if ($shouldSyncProducts) {
            Billingo_Logger::info('Termék szinkronizálás elindul számla létrehozáskor');
            $productSync = null;

            try {
                $productSync = new Billingo_Product_Sync();
            } catch (\Exception $e) {
                Billingo_Logger::error('Hiba a termék szinkronizáló szolgáltatás létrehozásakor: ' . $e->getMessage());
                // Folytassuk a dokumentum generálást termék szinkronizálás nélkül
                $shouldSyncProducts = false;
            }
        }

        // Bundle termékek azonosítása
        $bundleItems = [];
        $bundleMainProducts = [];
        $bundleData = []; // Bundle adatok tárolása a kedvezmény számításhoz

        // Első körben gyűjtsük össze a bundle részeket
        foreach ($items as $item) {
            $itemData = $item->get_data();
            $bundledBy = wc_get_order_item_meta($item->get_id(), '_bundled_by', true);

            if (!empty($bundledBy)) {
                // Ez egy bundle részterméke
                $bundleItems[$bundledBy][] = $item;
                Billingo_Logger::info('Bundle részterméke találva: ' . $itemData['name'] . ' (bundled by: ' . $bundledBy . ')');
            }
        }

        // Második körben gyűjtsük össze a bundle főtermékeket
        // A bundle főtermék az, amelyiknek van total értéke, de nincs _bundled_by meta mezője
        foreach ($items as $item) {
            $itemData = $item->get_data();
            $bundledBy = wc_get_order_item_meta($item->get_id(), '_bundled_by', true);
            $itemTotal = floatval($itemData['total'] ?? 0);

            // Ha nincs bundled_by és van total értéke, akkor potenciális bundle főtermék
            if (empty($bundledBy) && $itemTotal > 0) {
                // Ellenőrizzük, hogy YITH bundle termék-e
                $product = $item->get_product();
                $isBundleProduct = false;
                if ($product) {
                    // YITH Product Bundle specifikus meta ellenőrzése - több lehetséges meta mező
                    $bundledItems = get_post_meta($product->get_id(), '_yith_wcpb_bundled_items', true);
                    $yithBundleData = get_post_meta($product->get_id(), '_yith_wcpb_bundle_data', true);
                    $isBundle = get_post_meta($product->get_id(), '_yith_wcpb_bundle', true);

                    // Debug: minden meta mező kiírása
                    $allMeta = get_post_meta($product->get_id());
                    $yithMetas = [];
                    foreach ($allMeta as $key => $value) {
                        if (strpos($key, 'yith') !== false || strpos($key, 'bundle') !== false) {
                            $yithMetas[$key] = $value;
                        }
                    }
                    Billingo_Logger::info('YITH/Bundle meta mezők - ' . $itemData['name'] . ': ' . json_encode($yithMetas));

                    $isBundleProduct = !empty($bundledItems) || !empty($yithBundleData) || !empty($isBundle);
                }

                Billingo_Logger::info('Bundle ellenőrzés - ' . $itemData['name'] . ': YITH bundle=' . ($isBundleProduct ? 'igen' : 'nem'));

                // Csak akkor folytatjuk, ha YITH bundle termék
                if (!$isBundleProduct) {
                    Billingo_Logger::info('Nem YITH bundle termék, kihagyva: ' . $itemData['name']);
                    continue;
                }

                // Ellenőrizzük, hogy van-e bundle részterméke
                $foundBundleItems = null;
                $bundleKey = null;

                foreach ($bundleItems as $key => $bundleItemArray) {
                    // Ha találunk bundle részeket, ez valószínűleg a bundle főtermék
                    if (!empty($bundleItemArray)) {
                        // Számítsuk ki a bundle részek árának összegét
                        $bundleItemsTotalPrice = 0;
                        foreach ($bundleItemArray as $bundleItem) {
                            $bundleItemProduct = $bundleItem->get_product();
                            if ($bundleItemProduct) {
                                $bundleItemPrice = $this->getRegularPriceFromItemMeta($bundleItem, $bundleItemProduct);
                                $bundleItemData = $bundleItem->get_data();
                                $bundleItemsTotalPrice += $bundleItemPrice * ($bundleItemData['quantity'] ?? 1);
                            }
                        }

                        // Ha a bundle főtermék ára kisebb VAGY egyenlő, mint a részek összege, akkor ez valószínűleg bundled
                        // ÉS még nem használtuk fel ezt a bundle key-t
                        if ($itemTotal <= $bundleItemsTotalPrice && !isset($bundleData[$key])) {
                            $foundBundleItems = $bundleItemArray;
                            $bundleKey = $key;
                            break;
                        }
                    }
                }

                if ($foundBundleItems) {
                    $bundleMainProducts[] = $item;

                    // Bundle adatok tárolása a kedvezmény számításhoz
                    $bundleData[$bundleKey] = [
                        'main_item' => $item,
                        'bundled_items' => $foundBundleItems,
                        'bundle_sale_price' => $itemTotal,
                        'bundle_name' => $itemData['name'] ?? 'Bundle'
                    ];

                    Billingo_Logger::info('Bundle főtermék találva: ' . $itemData['name'] . ' (bundle key: ' . $bundleKey . ', sale price: ' . $itemTotal . ')');
                    Billingo_Logger::info('Bundle részek száma: ' . count($foundBundleItems));
                }
            }
        }

        Billingo_Logger::info('Összesen bundle főtermék: ' . count($bundleMainProducts));
        Billingo_Logger::info('Bundle adatok: ' . count($bundleData) . ' darab');

        // Termékek és termék-kedvezmények hozzáadása
        foreach ($items as $item) {
            $itemObject = $item;
            $itemData = $item->get_data();
            $product = $item->get_product();
            // A WooCommerce "Adóstátusz: Nincs" (tax_status = 'none') beállítása a terméken
            // teljesen független a tax_class-tól — a getCalculatedDateForItem() ez alapján
            // dönti el, hogy a normál tax_class-alapú ÁFA-számítás helyett a terméket
            // adómentesként kezelje.
            $itemData['tax_status'] = ($product && method_exists($product, 'get_tax_status'))
                ? $product->get_tax_status()
                : 'taxable';
            // A WooCommerce által erre a sorra ténylegesen felszámított áfa (ha megállapítható).
            $itemData['recorded_vat'] = $this->getRecordedVat($item);

            // Ellenőrizzük, hogy bundle részterméke-e vagy bundle főtermék-e
            $bundledBy = wc_get_order_item_meta($item->get_id(), '_bundled_by', true);
            $isBundleItem = !empty($bundledBy);

            if ($shouldSyncProducts && isset($productSync) && $product) {
                try {
                    $productSync->syncProduct($product, $itemData);
                } catch (\Exception $e) {
                    Billingo_Logger::error('Hiba a termék szinkronizálásakor: ' . $e->getMessage());
                }
            }
            Billingo_Logger::info('item tax class: ' . $itemData['tax_class']);
            // 0 (vagy negatív) mennyiségű sor nem számlázható és osztani sem lehet vele (DivisionByZeroError).
            $itemQuantity = (float)$item->get_quantity();
            if ($itemQuantity <= 0) {
                Billingo_Logger::warning('0 mennyiségű tétel kihagyva a számláról: ' . ($itemData['name'] ?? ''));
                continue;
            }

            $unitPriceorig = ($item->get_subtotal() + $item->get_subtotal_tax()) / $itemQuantity;
            $checkosszeg = ($item->get_total() + $item->get_total_tax()) / $itemQuantity;

            // Akciós terméknél (bekapcsolt "Kedvezmény külön tételként"): a sor a normál (akció előtti) áron szerepel, és a
            // külön "Kedvezmény" sor vonja le a különbséget. Korábban a sor már az akciós áron állt, és a kedvezmény sor
            // még egyszer levonta a különbséget (25 000 Ft-os, 17 500 Ft-ra akciózott termék: 10 000 Ft a 17 500 Ft helyett).
            $saleDiscountPerUnit = $isBundleItem ? 0.0 : $this->getSaleDiscountPerUnit($item, $itemData);
            $lineUnitPrice = $unitPriceorig + $saleDiscountPerUnit;

            try {
                $originalItem = new DocumentProductData([
                    'name' => $itemData['name'] ?? 'Termék',
                    'quantity' => $itemData['quantity'] ?? 1,
                    'unit_price' => $this->roundsToWholeAmounts()? (int) round($lineUnitPrice): $lineUnitPrice,
                    'unit_price_type' => $this->getCalculatedDateForItem('unit_price_type')->value,
                    'unit' => $this->getCalculatedDateForItem('unit'),
                    'vat' => $this->getCalculatedDateForItem('vat', $itemData, $countryCode)?->value ?? '0%',
                    'comment' => $this->getCalculatedDateForItem('comment', $itemData),
                    'entitlement' => $this->getCalculatedDateForItem('entitlement', $itemData)?->value,
                    'sku' => !empty($this->getProductSku($itemData)) ? $this->getProductSku($itemData) : null,
                    'is_generate_erase_code' => $this->hasEraseCode($itemObject),
                ]);
                // áfa felülírás ha kell
                if(wcFlexibleIsTrue(get_option('wc_billingo_tax_override'))){
                    $originalItem = $this->overrideTax($originalItem);
                }
                // a megjelölt cikkszámú tétel saját kulcsot kap (erősebb az általános felülírásnál)
                $originalItem = $this->applySkuVat($originalItem, $itemData);

                $productItems[] = $originalItem;
            } catch (\Exception $e) {
                Billingo_Logger::error('Hiba a dokumentum elem létrehozásakor: ' . $e->getMessage());
                // Folytatjuk a következő elemmel
                continue;
            }

            if($unitPriceorig != $checkosszeg){

                $dicountprice = $unitPriceorig-$checkosszeg;
                try {
                    $originalItemDiscount = new DocumentProductData([
                        'name' => $itemData['name'] . ' - kedvezmény' ?? 'Termék',
                        'quantity' => $itemData['quantity'] ?? 1,
                        'unit_price' => -($this->roundsToWholeAmounts()? (int) round($dicountprice): $dicountprice),
                        'unit_price_type' => $this->getCalculatedDateForItem('unit_price_type')->value,
                        'unit' => $this->getCalculatedDateForItem('unit'),
                        'vat' => $this->getCalculatedDateForItem('vat', $itemData, $countryCode)?->value ?? '0%',
                        'comment' => $this->getCalculatedDateForItem('comment', $itemData),
                        'entitlement' => $this->getCalculatedDateForItem('entitlement', $itemData)?->value,

                    ]);
                    // áfa felülírás ha kell
                    if(wcFlexibleIsTrue(get_option('wc_billingo_tax_override'))){
                        $originalItemDiscount = $this->overrideTax($originalItemDiscount);
                    }
                    $originalItemDiscount = $this->applySkuVat($originalItemDiscount, $itemData);

                    $productItems[] = $originalItemDiscount;
                } catch (\Exception $e) {
                    Billingo_Logger::error('Hiba a dokumentum elem létrehozásakor: ' . $e->getMessage());
                    // Folytatjuk a következő elemmel
                    continue;
                }
            }

            // Alkalmazzuk a tételenkénti kedvezményeket, ha engedélyezve van

            if (wc_get_order_item_meta($item->get_id(), '_wc_billingo_product_full_price_with_sale', true)){
                $productItems = $this->applyItemDiscounts($productItems, $item, $itemData, $product, $isBundleItem);
            }
        }
        // Kupon kedvezmények hozzáadása (ha engedélyezve van)
        $productItems = $this->applyCouponDiscounts($productItems, $items);

        // A termék-tételek (a szállítás és a díjak előtt): a "rendelést követő" díj-ÁFA ezek kulcsából számolódik.
        $orderProductItems = $productItems;

        // Szállítási költség hozzáadása a számlára
        $shippingMethods = $this->order->get_shipping_methods();
        $hasShippingCost = false;

        // Ellenőrizzük, hogy van-e tényleges szállítási költség
        foreach ($shippingMethods as $shippingMethod) {
            if (floatval($shippingMethod->get_total()) > 0) {
                $hasShippingCost = true;
                break;
            }
        }

        // Ha van szállítási költség, mindig hozzáadjuk a tényleges összegével
        if ($hasShippingCost) {
            Billingo_Logger::info('Szállítási költség található a rendelésben - hozzáadás a számlához');
            Billingo_Logger::info('SHIPPING : ' . $this->order->get_shipping_total() + $this->order->get_shipping_tax() );

            foreach ($shippingMethods as $shippingMethod) {
                $shippingMethodTitle = $shippingMethod->get_method_title();

                $shippingTotal = floatval($shippingMethod->get_total());
                $shippingPrice = $this->getShippingUnitPrice($shippingMethod);

                if ($shippingTotal > 0) {
                    // A WooCommerce által a szállításra felszámított áfa (vagy adómentesség) alapján
                    $shippingVat = $this->getShippingVatData($shippingMethod);

                    // Szállítási tétel létrehozása a tényleges összeggel
                    $shippingItem = new DocumentProductData(array_filter([
                        'name' => !empty($shippingMethodTitle)
                            ? $shippingMethodTitle
                            : __('Szállítás', 'billingo'),
                        'quantity' => 1,
                        'unit_price' => $this->roundsToWholeAmounts()
                            ? (int) round($shippingPrice['price']) : $shippingPrice['price'],
                        'unit_price_type' => $shippingPrice['type']->value,
                        'unit' => $this->getCalculatedDateForItem('unit'),
                        'vat' => $shippingVat['vat'],
                        'entitlement' => $shippingVat['entitlement'],
                        'comment' => ''
                    ], static fn($value) => $value !== null));

                    //áfa felülírás szállítási költség esetén is
                    if ($this->shouldOverrideCarrierTax()) {
                        $shippingItem = $this->overrideTax($shippingItem);
                    }
                    $productItems[] = $shippingItem;
                    Billingo_Logger::info('Szállítási tétel hozzáadva: ' . $shippingMethodTitle . ' (' . $shippingTotal . ' ' . $this->order->get_currency() . ')');
                }
            }

        }elseif (wcFlexibleIsTrue(get_option('wc_billingo_always_add_carrier')) && !$hasShippingCost && !empty($shippingMethods)) {
            // Ha a szállítási költség nulla, de a "mindig látszódjon" beállítás aktív, akkor 0 összegű tételt adunk hozzá
            Billingo_Logger::info('Nincs szállítási költség, de a "Szállító mindig látszódjon" beállítás aktív - 0 összegű tétel hozzáadása');

            if (!empty($shippingMethods)) {
                foreach ($shippingMethods as $shippingMethod) {
                    $shippingMethodTitle = $shippingMethod->get_method_title();

                    $shippingItem = new DocumentProductData([
                        'name' => !empty($shippingMethodTitle)
                            ? $shippingMethodTitle
                            : __('Szállítás', 'billingo'),
                        'quantity' => 1,
                        'unit_price' => 0,
                        'unit_price_type' => $this->getCalculatedDateForItem('unit_price_type')->value,
                        'unit' => $this->getCalculatedDateForItem('unit'),
                        'vat' => $this->getShippingVatCode($shippingMethod)->value,
                        'comment' => ''
                    ]);

                    // áfa felülírás ha kell
                    if ($this->shouldOverrideCarrierTax()) {
                        $shippingItem = $this->overrideTax($shippingItem);
                    }
                    $productItems[] = $shippingItem;
                    Billingo_Logger::info('Ingyenes szállítási tétel hozzáadva: ' . $shippingMethodTitle . ' (0 összegű)');

                    break;
                }
            }
        }

        $fees = $this->order->get_fees();
        Billingo_Logger::info('Tranzakciós költségek: ' . json_encode($fees));
        if (!empty($fees)) {
            Billingo_Logger::info('Tranzakciós költségek találhatók a rendelésben - hozzáadás a számlához');
            foreach ($fees as $fee) {
                $feeTotal = floatval($fee->get_total());
                $feeTax = floatval($fee->get_total_tax());
                $feeName = $fee->get_name();


                Billingo_Logger::info('Fee feldolgozása: ' . $feeName . ' - Total: ' . $feeTotal . ', Tax: ' . $feeTax);

                if ($feeTotal != 0) { // Pozitív vagy negatív összeg esetén is hozzáadjuk
                    $feeVatSetting = (string)get_option('wc_billingo_fee_vat_override', '');
                    // "A díj ÁFA kulcsa kövesse a rendelést" kapcsoló: a díj a rendelés termék-tételeinek ÁFA kulcsát
                    // kapja (több kulcsnál a legmagasabbat, mint a WooCommerce "a kosár tételei alapján" szállítási adója).
                    // A kapcsoló erősebb a kézzel választott kulcsnál; az csak akkor érvényes, ha nincs mit követni
                    // (pl. a rendelésen nincs termék-tétel).
                    $followedVat = wcFlexibleIsTrue(get_option('wc_billingo_fee_vat_follow_order'))
                        ? $this->getOrderFollowingVat($orderProductItems)
                        : null;
                    $hasManualFeeVatOverride = $feeVatSetting !== '' && $followedVat === null;

                    // Tranzakciós díj (pl. utánvétdíj) ÁFA kulcsának meghatározása: kézi felülírás
                    // nélkül elsődlegesen az, amit a WooCommerce a fee-re ténylegesen felszámolt —
                    // adómentes fee-nél (a WooCommerce nem adóztatta) az áfamentes kód a beállításokból.
                    $recordedFeeVat = (!$hasManualFeeVatOverride && $followedVat === null) ? $this->getRecordedVat($fee) : null;

                    // A fee saját "nem adóköteles" státusza is mentességet jelent akkor is, ha a rendelésről
                    // nincs adóadat (pl. adószámítás nélküli rendelés): különben a szabványos kulcs és a
                    // belőle visszaszámolt bruttó összeg a számlán a fizetett fee fölé nőne.
                    if ($recordedFeeVat === null
                        && !$hasManualFeeVatOverride
                        && $followedVat === null
                        && method_exists($fee, 'get_tax_status')
                        && $fee->get_tax_status() !== 'taxable'
                    ) {
                        $recordedFeeVat = $this->getExemptVatCodes();
                    }

                    if ($followedVat !== null) {
                        $feeVatCode = VatEnum::from($followedVat['vat']);
                        $feeEntitlement = $followedVat['entitlement'];
                        Billingo_Logger::info('Fee ÁFA kulcs a rendelés termékei alapján: ' . $feeVatCode->value);
                    } elseif ($recordedFeeVat !== null) {
                        $feeVatCode = VatEnum::from($recordedFeeVat['vat']);
                        $feeEntitlement = $recordedFeeVat['entitlement'];
                    } else {
                        try {
                            $feeVatCode = $this->getFeeVatCode($fee);
                        } catch (\Exception $e) {
                            // A getFeeVatCode() kivételt dob, ha semmilyen úton nem állapítható meg a kulcs;
                            // ez korábban fatal errorként kilépett a rendelés-állapot hookból.
                            $this->flagVatError(sprintf(
                                __('A(z) „%s” tranzakciós díj áfakulcsa nem állapítható meg. Állíts be kulcsot a „Tranzakciós díj (fee) ÁFA kulcsa” mezőben.', 'billingo'),
                                $feeName
                            ));
                            Billingo_Logger::error('Fee ÁFA kulcs hiba: ' . $e->getMessage());

                            continue;
                        }
                        $feeEntitlement = null;
                    }

                    Billingo_Logger::info('Fee ÁFA kulcs: ' . $feeVatCode->value);

                    // A WooCommerce "Az árak ÁFA-val vannak megadva" globális beállítása dönti el,
                    // hogy a fee összege (feeTotal) már eleve bruttó-e — ha igen, nem szabad még
                    // egyszer rátenni az ÁFA-t, mert "szuper bruttósítást" (duplázott ÁFA) okozna.
                    $pricesIncludeTax = wcFlexibleIsTrue(get_option('woocommerce_prices_include_tax'));

                    if ($feeTax != 0 || $followedVat !== null) {
                        // (A rendelést követő kulcsnál a vevő által fizetett összeg marad: ha a WC nem adóztatta a díjat,
                        // a fizetett összeg már tartalmazza az átvett kulcs szerinti áfát.)
                        // A WooCommerce már kiszámolt valós ÁFA összeget erre a fee-re: a vevő a fee nettó összegét
                        // és az áfát fizette (a WC a fee összegét mindig adó nélkül tárolja, az "árak ÁFA-val" beállítástól
                        // függetlenül). Ez kézi kulcs (a "Tranzakciós díj ÁFA kulcsa" beállítás) mellett is így van:
                        // az kizárólag az áfakulcsot cseréli, a számlán a vevő által fizetett összegnek kell szerepelnie.
                        $grossAmount = $feeTotal + $feeTax;
                    } else {
                        // Ha a WC nem számolt ÁFA-t a fee-re, a meghatározott (felülírt vagy automatikusan
                        // azonosított) kulcs alapján bruttósítunk — de csak akkor, ha az árak ténylegesen nettóban
                        // vannak megadva, egyébként a feeTotal-t változatlanul, bruttóként használjuk.
                        // A bruttósításhoz a számlára ténylegesen kerülő kulcsot használjuk: az általános ÁFA felülírás
                        // (pl. AAM) a díjat is átírja, ezért a felülírás előtti (pl. 27%-os) kulccsal számolva a díj a
                        // fizetett összegnél nagyobb lett volna (513 Ft helyett 651 Ft).
                        $vatRate = $this->getVatRateFromCode($this->getFinalFeeVatCode($feeVatCode->value, $hasManualFeeVatOverride, $followedVat !== null));
                        // Áfamentes EU-s rendelésnél a fee összege már eleve áfa nélküli, és a tétel
                        // 0%-os EU kóddal kerül a számlára, ezért nincs mit hozzátenni. Kikapcsolt adószámításnál
                        // a vevő pontosan a díj összegét fizette: nincs mit felbruttósítani.
                        $grossAmount = ($pricesIncludeTax || $this->isVatExemptOrder() || !wc_tax_enabled())
                            ? $feeTotal
                            : $feeTotal * (1 + $vatRate / 100);
                    }

                    $usedvatcode = $feeVatCode->value;

                    $feeItem = new DocumentProductData(array_filter([
                        'name' => !empty($feeName)
                            ? $feeName
                            : __('Tranzakciós költség', 'billingo'),
                        'quantity' => 1,
                        'unit_price' => $this->roundsToWholeAmounts()
                            ? (int) round($grossAmount) : $grossAmount,
                        'unit_price_type' => UnitPriceTypeEnum::GROSS->value,
                        'unit' => $this->getCalculatedDateForItem('unit'),
                        'vat' => $usedvatcode,
                        'entitlement' => $feeEntitlement,
                        'comment' => ''
                    ], static fn($value) => $value !== null));
                    // Általános ÁFA felülírás ha kell — de a "Tranzakciós díj (fee) ÁFA kulcsa" beállítás a díjra
                    // specifikusabb, ezért azt nem írja felül az általános (pl. a termékekre szánt kedvezményes)
                    // kulcs: így a díj (pl. utánvétdíj) következetesen a megadott standard kulcsot kapja.
                    // (A rendelést követő díj a már felülírt termék-tételek kulcsát veszi át, nem kell újra felülírni.)
                    if (wcFlexibleIsTrue(get_option('wc_billingo_tax_override')) && !$hasManualFeeVatOverride && $followedVat === null) {
                        $feeItem = $this->overrideTax($feeItem);
                    }

                    $productItems[] = $feeItem;
                    Billingo_Logger::info('Tranzakciós költség tétel hozzáadva: ' . $feeName . ' (' . $grossAmount . ' ' . $this->order->get_currency() . ') ÁFA: ' . $feeVatCode->value);
                }
            }
        }

        return $productItems;
    }

    /**
     * Szállítási ÁFA kulcs meghatározása
     * @param mixed $shippingMethod WooCommerce szállítási módszer objektum (opcionális)
     * @return VatEnum
     */
    private function getShippingVatCode($shippingMethod = null): VatEnum
    {
        // 1) Ha van kiszámolt shipping rate (WC_Shipping_Rate / WC_Order_Item_Shipping), abból próbáljuk
        if ($shippingMethod && method_exists($shippingMethod, 'get_taxes')) {
            $taxes = $shippingMethod->get_taxes();
            // A WC_Order_Item_Shipping::get_taxes() ['total' => [rate_id => amount], 'subtotal' => [...]]
            // alakú tömböt ad vissza, a WC_Shipping_Rate::get_taxes() pedig közvetlenül [rate_id => amount]
            // szerkezetet — mindkettőt kezeljük, különben a kulcsok ('total', 'subtotal') nem rate_id-k,
            // és a számla mindig a 27%-os alapértelmezésre esne vissza.
            if (is_array($taxes) && (isset($taxes['total']) || isset($taxes['subtotal']))) {
                $taxes = !empty($taxes['total']) && is_array($taxes['total'])
                    ? $taxes['total']
                    : (is_array($taxes['subtotal'] ?? null) ? $taxes['subtotal'] : []);
            }
            if (is_array($taxes) && !empty($taxes)) {
                // Keressünk egy olyan kulcsot, ami tényleg tax_rate_id (és nem 0 amount)
                foreach ($taxes as $taxRateId => $amount) {
                    if ($amount === null) continue;
                    if ((float)$amount <= 0) continue;

                    // taxRateId lehet string, castoljuk
                    $taxRateId = (int)$taxRateId;
                    if ($taxRateId > 0) {
                        $taxRate = WC_Tax::_get_tax_rate($taxRateId); // ['tax_rate' => '27.0000', ...]
                        if (!empty($taxRate['tax_rate'])) {
                            return VatEnum::fromNumber((float)$taxRate['tax_rate']) ?? VatEnum::PERCENT_27;
                        }
                    }
                }
            }
        }

        // 2) Fallback: kérjük le a shipping tax rate-eket a WC saját logikájával
        // (ez figyelembe veszi, hogy a tax rate sorban be van-e pipálva a "Szállítás")
        $taxClass = get_option('woocommerce_shipping_tax_class', ''); // ''=standard, reduced-rate, zero-rate, inherit

        // Ha "inherit" (based on items in cart), akkor nincs fix shipping ÁFA kulcs – itt maradunk defaulton
        if ($taxClass !== 'inherit') {
            // A WC_Tax::find_shipping_rates a shipping-re alkalmazható rate-eket adja (tax_rate_shipping=1 alapján),
            // a rendelés adózási helye szerint. Korábban a WC()->customer címe számított: kézi (admin) számlázásnál
            // az éppen belépett adminé, automatikusnál a bolt székhelye — ugyanarra a rendelésre más-más kulcs jött ki.
            $rates = WC_Tax::find_shipping_rates(array_merge($this->getOrderTaxLocation(), ['tax_class' => $taxClass]));
            if (is_array($rates) && !empty($rates)) {
                $first = reset($rates); // a prioritás szerinti első
                if (is_array($first) && isset($first['rate'])) {
                    return VatEnum::fromNumber((float)$first['rate']) ?? VatEnum::PERCENT_27;
                }
            }
        }

        // 3) Default (HU standard)
        return VatEnum::PERCENT_27;
    }


    /**
     * Tranzakciós díj ÁFA kulcs meghatározása
     * @param mixed $fee WooCommerce fee objektum
     * @return VatEnum
     */
    private function getFeeVatCode($fee): VatEnum
{
    Billingo_Logger::info(
        'Fee ÁFA kulcs meghatározása - Fee objektum típusa: ' .
        (is_object($fee) ? get_class($fee) : gettype($fee))
    );

    // 0) Admin által kézzel beállított, kötelező ÁFA kulcs a fee-khez — ha be van
    // állítva, ez mindig elsőbbséget élvez az automatikus (gyakran megbízhatatlan,
    // mert sok fizetési/fee modul hibásan állítja be a saját ÁFA-adatait) kikövetkeztetés
    // helyett.
    $feeVatOverride = get_option('wc_billingo_fee_vat_override', '');

    if (!empty($feeVatOverride)) {
        $vatEnum = VatEnum::tryFrom($feeVatOverride);

        if ($vatEnum) {
            Billingo_Logger::info('Fee ÁFA kulcs admin felülírás alapján: ' . $vatEnum->value);

            return $vatEnum;
        }
    }

    // 1) Próbáljuk a rate_id-t kinyerni a get_taxes() struktúrából
    if ($fee && method_exists($fee, 'get_taxes')) {
        $taxes = $fee->get_taxes();

        Billingo_Logger::info('Fee taxes: ' . json_encode($taxes));

        $rateId = null;

        if (is_array($taxes)) {
            if (!empty($taxes['total']) && is_array($taxes['total'])) {
                $rateId = $this->firstTaxRateId($taxes['total']);
            } elseif (!empty($taxes['subtotal']) && is_array($taxes['subtotal'])) {
                $rateId = $this->firstTaxRateId($taxes['subtotal']);
            } elseif (!isset($taxes['total']) && !isset($taxes['subtotal'])) {
                // fallback: ha közvetlen rate_id => amount struktúra jönne
                $rateId = $this->firstTaxRateId($taxes);
            }
        }

        if ($rateId !== null) {
            $taxRate = WC_Tax::_get_tax_rate($rateId);

            Billingo_Logger::info(
                'Tax rate data (rate_id=' . $rateId . '): ' .
                json_encode($taxRate)
            );

            if (is_array($taxRate) && isset($taxRate['tax_rate'])) {
                $rateNumber = (float)$taxRate['tax_rate'];
                $vatEnum = VatEnum::fromNumber($rateNumber);

                if ($vatEnum) {
                    Billingo_Logger::info(
                        'Fee ÁFA kulcs megtalálva taxes alapján: ' .
                        $vatEnum->value .
                        ' (' . $rateNumber . '%)'
                    );

                    return $vatEnum;
                }
            }
        }
    }

    // 2) Fee saját tax class alapján
    // Fontos: WooCommerce standard tax class = üres string ''
    // Ezért az üres tax class-t is kezelni kell.
    if ($fee && method_exists($fee, 'get_tax_class')) {
        $taxClass = (string)$fee->get_tax_class();

        Billingo_Logger::info('Fee tax class: ' . $taxClass);

        $vatEnum = $this->getVatEnumByTaxClass($taxClass);

        if ($vatEnum) {
            Billingo_Logger::info(
                'Fee ÁFA kulcs fee tax class alapján: ' .
                $vatEnum->value .
                ' (tax_class=' . $taxClass . ')'
            );

            return $vatEnum;
        }
    }

    // 3) Ha a fee-ből nem derült ki, próbáljuk a rendelés termékeiből.
    // Vegyes ÁFA esetén itt nem választunk találomra, hanem továbbengedjük standard fallbackre.
    $order = $this->getOrderFromFee($fee);

    if ($order instanceof WC_Order) {
        $vatEnum = $this->getVatEnumFromOrderProductTaxClasses($order);

        if ($vatEnum) {
            Billingo_Logger::info(
                'Fee ÁFA kulcs rendelés termékeinek egységes ÁFA kulcsa alapján: ' .
                $vatEnum->value
            );

            return $vatEnum;
        }
    }

    // 4) Végső fallback: WooCommerce standard tax class
    // WooCommerce-ben a standard tax class kulcsa üres string.
    $vatEnum = $this->getVatEnumByTaxClass('');

    if ($vatEnum) {
        Billingo_Logger::info(
            'Fee ÁFA kulcs WooCommerce standard tax class alapján: ' .
            $vatEnum->value
        );

        return $vatEnum;
    }

    // 5) Ha semmi nem található, akkor dobunk hibát
    Billingo_Logger::error(
        'Fee ÁFA kulcs nem található. Fee adatok: ' .
        json_encode([
            'fee_class' => is_object($fee) ? get_class($fee) : gettype($fee),
            'taxes' => ($fee && method_exists($fee, 'get_taxes')) ? $fee->get_taxes() : null,
            'tax_class' => ($fee && method_exists($fee, 'get_tax_class')) ? $fee->get_tax_class() : null,
            'order_id' => ($fee && method_exists($fee, 'get_order_id')) ? $fee->get_order_id() : null,
        ])
    );

    throw new \Exception('Fee VAT rate could not be determined.');
}


   private function getOrderFromFee($fee): ?WC_Order
{
    if ($fee && method_exists($fee, 'get_order_id')) {
        $orderId = (int)$fee->get_order_id();

        if ($orderId > 0) {
            $order = wc_get_order($orderId);

            if ($order instanceof WC_Order) {
                return $order;
            }
        }
    }

    if ($fee && method_exists($fee, 'get_order')) {
        $order = $fee->get_order();

        if ($order instanceof WC_Order) {
            return $order;
        }
    }

    return null;
}

    /**
     * A számla végösszegének kerekítése: a beállított "5-re / 10-re / 1-re" kerekítés a forint fillér nélküli
     * készpénzes kerekítésére való. Más devizánál nem alkalmazzuk, különben a 33,15 EUR végösszeg 35 EUR lenne a számlán.
     */
    private function getInvoiceRounding(string $currency): mixed
    {
        $rounding = get_option('wc_billingo_invoice_round');

        return strtoupper($currency) === 'HUF' ? $rounding : RoundEnum::NONE->value;
    }

    /**
     * Egész összegre kerekítünk-e: a "Tizedes jegyek használata számla készítésénél" kikapcsolt állapota a forintra szól
     * (nincs fillér). Más devizánál (pl. EUR) a tizedesjegyek mindig megmaradnak: korábban a 12,34 EUR is 12 EUR lett
     * a számlán, így a végösszeg és a nettó értékek is eltértek a rendeléstől.
     */
    private function roundsToWholeAmounts(): bool
    {
        if (wcFlexibleIsTrue(get_option('wc_billingo_decimalsoff'))) {
            return false;
        }

        $currency = $this->order instanceof WC_Order ? strtoupper((string)$this->order->get_currency()) : '';

        return $currency === '' || $currency === 'HUF';
    }

    /**
     * A díj tétel végleges áfakódja a számlán: a kézi díj-kulcs és a rendelést követő kulcs nem íródik felül, az
     * általános ÁFA felülírás (ha be van kapcsolva) viszont igen.
     */
    private function getFinalFeeVatCode(string $vatCode, bool $manualKey, bool $followsOrder): string
    {
        if ($manualKey || $followsOrder || !wcFlexibleIsTrue(get_option('wc_billingo_tax_override'))) {
            return $vatCode;
        }

        $probe = new DocumentProductData([
            'name' => 'díj', 'quantity' => 1, 'unit_price' => 1, 'unit_price_type' => UnitPriceTypeEnum::GROSS->value,
            'unit' => 'db', 'vat' => $vatCode,
        ]);
        $probe = $this->overrideTax($probe);

        return (string)($probe->vat ?? $vatCode);
    }

    /**
     * Az áfamentes áfakódhoz tartozó jogcím: a legtöbb kódnál azonos nevű (AAM, TAM...), az EUK (nem uniós kivitel)
     * kódhoz viszont a Billingo kizárólag az EAM jogcímet fogadja el ("Available values EAM"), az EUK nevűt nem.
     * Százalékos kulcsnál nincs jogcím.
     */
    private function entitlementForVatCode(string $vatCode): ?string
    {
        if ($vatCode === VatEnum::EUK->value) {
            return EntitlementEnum::EAM->value;
        }

        return EntitlementEnum::tryFrom($vatCode)?->value;
    }

    /**
     * Az áfamentes tételek (a WooCommerce nem adóztatta) áfakódja és jogcíme a "0% ÁFA felülíráshoz
     * tartozó jogcím" beállításból (alapértelmezés: TAM). Ha a jogcím egyben érvényes áfakód
     * (pl. AAM, TAM), az megy áfakódként is; egyébként (pl. KBAET, EAM) AM áfakód + külön jogcím.
     *
     * @return array{vat: string, entitlement: ?string}
     */
    private function getExemptVatCodes(): array
    {
        $code = get_option('wc_billingo_tax_override_zero_entitlements') ?: EntitlementEnum::TAM->value;

        $vatEnum = VatEnum::tryFrom($code);
        $entitlementEnum = EntitlementEnum::tryFrom($code);

        if ($vatEnum !== null) {
            return ['vat' => $vatEnum->value, 'entitlement' => $this->entitlementForVatCode($vatEnum->value)];
        }

        if ($entitlementEnum !== null) {
            return ['vat' => VatEnum::AM->value, 'entitlement' => $entitlementEnum->value];
        }

        return ['vat' => VatEnum::TAM->value, 'entitlement' => EntitlementEnum::TAM->value];
    }

    /**
     * A megadott WooCommerce adókulcs (rate_id) százalékos értéke: elsődlegesen a rendelés saját
     * adósoraiból (a vásárláskor érvényes érték), tartaléknak az adótáblából.
     */
    private function getRatePercent(int $rateId): ?float
    {
        if ($this->orderTaxRates === null) {
            $this->orderTaxRates = [];

            foreach ($this->order->get_items('tax') as $taxItem) {
                if (method_exists($taxItem, 'get_rate_id') && method_exists($taxItem, 'get_rate_percent')) {
                    $percent = $taxItem->get_rate_percent();

                    if ($percent !== false && $percent !== null) {
                        $this->orderTaxRates[(int)$taxItem->get_rate_id()] = (float)$percent;
                    }
                }
            }
        }

        if (isset($this->orderTaxRates[$rateId])) {
            return $this->orderTaxRates[$rateId];
        }

        $taxRate = WC_Tax::_get_tax_rate($rateId);

        return is_array($taxRate) && isset($taxRate['tax_rate']) ? (float)$taxRate['tax_rate'] : null;
    }

    /**
     * A HUF-tól eltérő deviza árfolyama. Ha a Billingo nem ad érvényes árfolyamot, a generálás megáll
     * (a korábbi 1.0-s tartalék árfolyam hibás HUF-összegű számlát eredményezett).
     */
    private function resolveConversionRate(string $currency): float
    {
        $rate = $this->getCurrencyRate($currency, 'HUF');

        if ($rate === null || !is_numeric($rate) || (float)$rate <= 0) {
            $this->flagStopError(sprintf(
                __('A(z) %s deviza árfolyamát nem sikerült lekérni a Billingótól, ezért a bizonylat nem készült el.', 'billingo'),
                $currency
            ));

            return 1.0; // csak helyőrző: a make() a hiba miatt úgysem küldi el a dokumentumot
        }

        return (float)$rate;
    }

    /**
     * "A generálás megáll" hiba, amely semmilyen beállítással nem kerülhető meg (pl. hiányzó árfolyam).
     */
    private function flagStopError(string $message): void
    {
        if ($this->stopError === null) {
            $this->stopError = $message;
        }
    }

    /**
     * ÁFA-kulcs hiba: a generálás megáll, hibás számla kiállítása helyett, KIVÉVE ha az érintett tételek
     * kódját úgyis felülírja az áfamentes EU-s rendelés vagy az ÁFA felülírás (lásd make()).
     */
    private function flagVatError(string $message): void
    {
        if ($this->vatError === null) {
            $this->vatError = $message;
        }
    }

    /**
     * A WooCommerce által a rendelés adott sorára (termék, szállítás, fee) ténylegesen felszámított
     * áfa alapján adja vissza az áfakódot — nem az adóosztályból és a számlázási országból találja ki.
     *
     * - adót számolt a WooCommerce: a felszámított kulcs;
     * - az árak adózásra készültek (a rendelésen van adó), de erre a sorra nem számolt: áfamentes
     *   kód a beállításokból (pl. adómentes utánvétdíj vagy szállítás);
     * - nincs elég adat (adószámítás kikapcsolva, 0 összegű sor, adó nélküli rendelés): null, ilyenkor
     *   a korábbi, adóosztály-alapú logika dolgozik.
     *
     * @return array{vat: string, entitlement: ?string}|null
     */
    private function getRecordedVat(mixed $orderItem): ?array
    {
        if (!function_exists('wc_tax_enabled') || !wc_tax_enabled()) {
            return null;
        }

        if (!is_object($orderItem) || !method_exists($orderItem, 'get_taxes') || !($this->order instanceof WC_Order)) {
            return null;
        }

        $lineTotal = (float)$orderItem->get_total();

        if ($lineTotal == 0.0) {
            return null;
        }

        $taxes = $orderItem->get_taxes();
        $rates = is_array($taxes) && !empty($taxes['total']) && is_array($taxes['total']) ? $taxes['total'] : [];

        // A WooCommerce a rendelés adminisztrációs felületen való mentésekor a nem adózott sorokhoz is ír üres összegű
        // adósort ([13 => '']): ez nem felszámított adó, így nem lehet kulcsot belőle olvasni. Korábban az ilyen díj
        // (pl. adómentes MPL költség) 27%-os kulcsot kapott a végszámlán, az előlegszámlán viszont áfamentes volt, ezért
        // a Billingo elutasította az előleg felhasználását ("VAT types do not match"). A 0,00-s összeg (0%-os kulcs) marad.
        $rates = array_filter($rates, static fn($amount) => $amount !== '' && $amount !== null);

        if (empty($rates)) {
            // Csak akkor tekintjük szándékosan adómentesnek, ha a rendelés más soraira a WooCommerce
            // számolt adót. Teljesen adó nélküli rendelésnél (kézi rendelés, kikapcsolt adószámítás)
            // nem tudjuk, hogy szándékos volt-e, ott a korábbi logika marad.
            // A rögzített adósor (akár 0%-os kulccsal) is azt jelenti, hogy a WooCommerce számolt adót.
            if ((float)$this->order->get_total_tax() <= 0 && empty($this->order->get_items('tax'))) {
                return null;
            }

            return $this->getExemptVatCodes();
        }

        $lineTax = (float)$orderItem->get_total_tax();
        $percent = null;

        if (count($rates) === 1) {
            $percent = $this->getRatePercent((int)array_key_first($rates));
        } else {
            $taxed = array_filter($rates, static fn($amount) => (float)$amount > 0);

            if (count($taxed) === 1) {
                $percent = $this->getRatePercent((int)array_key_first($taxed));
            }
        }

        // Több (pl. összetett) kulcsnál vagy ismeretlen kulcsnál a ténylegesen felszámított adóból
        // számolt tényleges százalék.
        if ($percent === null && $lineTax > 0) {
            $percent = round($lineTax / $lineTotal * 100, 2);
        }

        if ($percent === null) {
            return null;
        }

        // Egész számra kerekíteni tilos (a 13,5% nem lehet 14%): a Billingo kulcsai közül csak a pontos,
        // vagy legfeljebb egy tizedesre kerekített (számított, pl. összetett adó) érték fogadható el.
        foreach ([$percent, round($percent, 1)] as $candidate) {
            $vatEnum = VatEnum::fromNumber((string)$candidate);

            if ($vatEnum !== null) {
                return ['vat' => $vatEnum->value, 'entitlement' => null];
            }
        }

        $this->flagVatError(sprintf(
            /* translators: 1: tétel neve, 2: áfakulcs százalék */
            __('A WooCommerce által felszámított %2$s%% áfakulcs („%1$s”) nem szerepel a Billingo támogatott áfakulcsai között.', 'billingo'),
            method_exists($orderItem, 'get_name') ? $orderItem->get_name() : '',
            rtrim(rtrim(number_format($percent, 2, ',', ''), '0'), ',')
        ));

        return null;
    }

    /**
     * Szállítási sor áfakódja és jogcíme: elsődlegesen a WooCommerce által felszámított adó alapján.
     *
     * @return array{vat: string, entitlement: ?string}
     */
    /**
     * A szállítási díj számlán feltüntetett módja (beállítás): bruttó (alapértelmezett) vagy nettó.
     */
    private function getShippingPriceMode(): string
    {
        return get_option('wc_billingo_shipping_price_mode') === self::SHIPPING_PRICE_NET
            ? self::SHIPPING_PRICE_NET
            : self::SHIPPING_PRICE_GROSS;
    }

    /**
     * A szállítási sor egységára és árának típusa. A WooCommerce a szállítás összegét MINDIG adó nélkül tárolja
     * (a megadás módjától függetlenül), az adót külön (get_total_tax) rögzíti — ezért a vevő által fizetett
     * bruttó összeg mindig összeg + adó. Bruttó módban ez kerül a számlára (a végösszeg egyezik a fizetettel),
     * nettó módban az adó nélküli összeg, amihez a Billingo a tétel áfakulcsa szerint számol áfát.
     *
     * @return array{price: float, type: UnitPriceTypeEnum}
     */
    private function getShippingUnitPrice($shippingMethod): array
    {
        $net = floatval($shippingMethod->get_total());

        if ($this->getShippingPriceMode() === self::SHIPPING_PRICE_NET) {
            return ['price' => $net, 'type' => UnitPriceTypeEnum::NET];
        }

        return ['price' => $net + floatval($shippingMethod->get_total_tax()), 'type' => UnitPriceTypeEnum::GROSS];
    }

    private function getShippingVatData($shippingMethod): array
    {
        $recorded = $this->getRecordedVat($shippingMethod);

        if ($recorded !== null) {
            return $recorded;
        }

        return ['vat' => $this->getShippingVatCode($shippingMethod)->value, 'entitlement' => null];
    }

    /**
     * Van-e a rendelésben pozitív összegű szállítási díj (ugyanaz a feltétel, ami alapján a
     * szállítási tétel a számlára kerül).
     */
    private function hasPositiveShippingCost(): bool
    {
        if (!($this->order instanceof WC_Order)) {
            return false;
        }

        foreach ($this->order->get_shipping_methods() as $shippingMethod) {
            if (floatval($shippingMethod->get_total()) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Áfamentes (fordított adózású) EU-s rendelés-e: a WooCommerce a rendelést áfamentesnek jelölte
     * (érvényes EU-s adószám, pl. VIES-ellenőrzés után), vagy a nem magyar EU-s céges vevő
     * rendelésére a WooCommerce 0 áfát számolt. Ilyenkor a termék adóosztályából visszaszámolt
     * kulcs hibás lenne, "EU" áfakód és "KBAET" jogcím kell.
     */
    private function isVatExemptOrder(): bool
    {
        if ($this->vatExemptOrder !== null) {
            return $this->vatExemptOrder;
        }

        $this->vatExemptOrder = false;
        $this->orderExemptionCodes = null;

        if (!($this->order instanceof WC_Order)) {
            return false;
        }

        $country = strtoupper((string)$this->order->get_billing_country());
        $euCountries = function_exists('WC') && WC()->countries ? WC()->countries->get_european_union_countries() : [];
        $markedExempt = $this->order->get_meta('is_vat_exempt') === 'yes';
        $total = (float)$this->order->get_total();
        $totalTax = (float)$this->order->get_total_tax();

        // 1) Uniós (nem magyar) számlázási országú rendelés: megjelölt adómentesség, vagy zéró adó + uniós adószám.
        if ($country !== '' && $country !== 'HU' && in_array($country, $euCountries, true)) {
            $zeroTaxWithVatNumber = $total > 0 && $totalTax == 0.0 && !empty($this->getOrderVatNumber());

            if ($markedExempt || $zeroTaxWithVatNumber) {
                $this->orderExemptionCodes = ['vat' => VatEnum::EU->value, 'entitlement' => EntitlementEnum::KBAET->value];
                Billingo_Logger::info('Áfamentes EU-s rendelés felismerve (ország: ' . $country . ') - EU/KBAET áfakód kerül a tételekre.');
            }
        }

        // 2) Az adózási hely (alapból a szállítási cím) szerint nem uniós rendelés, amelyre a WooCommerce nem számolt
        //    adót: kivitel (harmadik országba értékesítés), EUK kóddal. Korábban az ilyen tételek az adóosztályból
        //    kitalált (pl. 27% / 10%) kulcsot kapták, holott a vevő áfa nélkül fizetett.
        //    Megjelölt adómentes (is_vat_exempt) nem uniós vevő is kivitelnek számít. Kikapcsolt adószámításnál
        //    (kézi rendelés) nincs adatunk, ott a régi logika dolgozik.
        $taxCountry = $this->getOrderTaxLocation()['country'];

        if ($this->orderExemptionCodes === null && $total > 0 && $totalTax == 0.0) {
            $isNonEu = $taxCountry !== '' && $taxCountry !== 'HU' && !in_array($taxCountry, $euCountries, true);

            if ($isNonEu && (wc_tax_enabled() || $markedExempt)) {
                $this->orderExemptionCodes = [
                    'vat' => VatEnum::EUK->value,
                    'entitlement' => $this->entitlementForVatCode(VatEnum::EUK->value),
                ];
                Billingo_Logger::info('Nem uniós rendelés, a WooCommerce nem számolt adót (ország: ' . $taxCountry . ') - EUK áfakód kerül a tételekre.');
            }
            // Magyar vevő soha nem adómentes a megjelölés alapján: a belföldi értékesítés ÁFA-köteles marad.
        }

        $this->vatExemptOrder = $this->orderExemptionCodes !== null;

        return $this->vatExemptOrder;
    }

    /**
     * A megadott adószám a beállított egyedi meta mezőből (ha van).
     */
    private function getOrderVatNumber(): ?string
    {
        $metaKey = trim((string)get_option('wc_billingo_vat_number_form_custom'));

        if ($metaKey !== '') {
            // A pénztármező-szerkesztő bővítmények (és a saját mezők) a meta kulcsot aláhúzással (_billing_adoszam) és
            // anélkül (billing_adoszam) is menthetik; korábban csak az aláhúzásos változatot olvastuk, így az utóbbi
            // adószáma nem jutott el a számlához. A beírt kulcs mindkét változatát megpróbáljuk.
            $bareKey = ltrim($metaKey, '_');
            $candidateKeys = array_values(array_unique(['_' . $bareKey, $metaKey, $bareKey]));

            foreach ($candidateKeys as $candidateKey) {
                $vatNumber = $this->order->get_meta($candidateKey, true);

                if (!empty($vatNumber) && is_scalar($vatNumber)) {
                    return trim((string)$vatNumber);
                }
            }

            Billingo_Logger::info('Az adószám egyedi meta mezője (' . $metaKey . ') üres a rendelésen, vizsgált kulcsok: ' . implode(', ', $candidateKeys));
        }

        // A pénztár saját adószám mezője (Adószám mező megjelenítése vásárláskor): eddig a megadott adószám
        // nem jutott el a számlához, ha nem volt külön egyedi meta mező beállítva.
        if (wcFlexibleIsTrue(get_option('wc_billingo_vat_number_form'))) {
            $own = Billingo_Checkout_Fields::get_vat_number_from_order($this->order);

            if ($own !== '') {
                return Billingo_Checkout_Fields::normalize_hungarian_vat_number($own) ?? $own;
            }
        }

        return null;
    }

    /**
     * Áfamentes rendelésnél (EU-s adómentes: EU / KBAET; nem uniós kivitel: EUK; adómentesnek jelölt hazai vevő: a
     * beállított áfamentes kód) minden tételt a rendelés kódjával látunk el.
     * Az admin ÁFA felülírás ilyenkor nem érvényesül, mert hibás (pl. 27%-os) kulcsot erőltetne
     * egy valójában áfamentes ügyletre.
     */
    private function applyOrderVatExemption(array $items): array
    {
        if (!$this->isVatExemptOrder()) {
            return $items;
        }

        $codes = $this->orderExemptionCodes;

        foreach ($items as $item) {
            $item->vat = $codes['vat'];
            $item->entitlement = $codes['entitlement'];
        }

        return $items;
    }

    /**
     * Visszaadja az ÁFA kulcs százalékos értékét a kód alapján
     */
    /**
     * Cikkszám szerinti ÁFA kulcs: a megadott cikkszámú (pl. DRS) tételek a beállított kulcsot / jogcímet kapják
     * (pl. TAM), az általános ÁFA felülírástól függetlenül. A cikkszám egyezése kis-nagybetűtől független, a *
     * helyettesítő karakter használható (pl. DRS*); a lista elválasztója a vessző, pontosvessző vagy sortörés.
     *
     * @return array{skus: string[], vat: string, entitlement: ?string}|null null, ha a szabály ki van kapcsolva vagy hiányos
     */
    private function getSkuVatRule(): ?array
    {
        if (!wcFlexibleIsTrue(get_option('wc_billingo_sku_vat_enabled'))) {
            return null;
        }

        $skus = array_values(array_filter(array_map(
            static fn($sku) => strtolower(trim($sku)),
            preg_split('/[,;\r\n]+/', (string)get_option('wc_billingo_sku_vat_skus', '')) ?: []
        ), static fn($sku) => $sku !== ''));

        $vatEnum = VatEnum::tryFrom((string)get_option('wc_billingo_sku_vat_code', 'TAM'));

        if ($skus === [] || $vatEnum === null) {
            return null;
        }

        return [
            'skus' => $skus,
            'vat' => $vatEnum->value,
            // az áfamentes kódok (TAM, AAM...) jogcíme azonos nevű; százalékos kulcsnál nincs jogcím
            'entitlement' => $this->entitlementForVatCode($vatEnum->value),
        ];
    }

    private function skuMatchesVatRule(string $sku, array $rule): bool
    {
        $sku = strtolower(trim($sku));

        if ($sku === '') {
            return false;
        }

        foreach ($rule['skus'] as $pattern) {
            if ($pattern === $sku || (str_contains($pattern, '*') && fnmatch($pattern, $sku))) {
                return true;
            }
        }

        return false;
    }

    private function applySkuVat(DocumentProductData $item, ?array $itemData): DocumentProductData
    {
        $rule = $this->getSkuVatRule();

        if ($rule === null || !$this->skuMatchesVatRule($this->getProductSku($itemData), $rule)) {
            return $item;
        }

        $item->vat = $rule['vat'];
        $item->entitlement = $rule['entitlement'];
        Billingo_Logger::info('Cikkszám szerinti ÁFA kulcs: ' . $rule['vat'] . ' — ' . ($itemData['name'] ?? ''));

        return $item;
    }

    /**
     * A díj ÁFA kulcsa a rendelés termék-tételei alapján: a tételek kulcsai közül a legmagasabb számszerű (azonos
     * kulcsnál az első); csak áfamentes kódok esetén az első tétel kódja és jogcíme. A negatív (kedvezmény) sorok nem
     * számítanak. Null, ha nincs termék-tétel — ilyenkor a megszokott díj-kulcs meghatározás dolgozik.
     *
     * @param DocumentProductData[] $items
     * @return array{vat: string, entitlement: ?string}|null
     */
    private function getOrderFollowingVat(array $items): ?array
    {
        $best = null;
        $bestRate = -1.0;

        foreach ($items as $item) {
            if (!($item instanceof DocumentProductData)) {
                continue;
            }

            $vat = (string)($item->vat ?? '');

            if ($vat === '' || (float)($item->unit_price ?? 0) <= 0) {
                continue;
            }

            $rate = $this->getVatRateFromCode($vat);

            if ($rate > $bestRate) {
                $best = ['vat' => $vat, 'entitlement' => isset($item->entitlement) ? (string)$item->entitlement : null];
                $bestRate = $rate;
            }
        }

        return $best;
    }

    private function getVatRateFromCode(string $vatCode): float
    {
        // Alapértelmezett érték: 27%
        $defaultRate = 27.0;

        // A szöveges kódok (EU, AM, TAM, EUK...) nem tartalmaznak számot: ezek nem százalékos,
        // áfamentes kulcsok, a számításban 0%-ként kezeljük őket.
        if (!preg_match('/\d/', $vatCode)) {
            return $vatCode === '' ? $defaultRate : 0.0;
        }

        // A tizedesvessző pontra cserélése (pl. "5,5%"), majd a nem numerikus karakterek eltávolítása
        $vatRate = preg_replace('/[^0-9\.]/', '', str_replace(',', '.', $vatCode));
        return floatval($vatRate);
    }

    private function firstTaxRateId(array $taxAmountsByRateId): ?int
    {
        foreach ($taxAmountsByRateId as $rateId => $amount) {

            if (is_numeric($rateId)) {
                return (int)$rateId;
            }
        }

        return null;
    }

    /**
     * Az adóosztály kulcsai közül a rendelés adózási helyének (országának) megfelelő; ha nincs ilyen, a bolt
     * székhelyének országáé; végül (régi viselkedés) az első. A WC_Tax::get_rates_for_tax_class() az összes ország
     * kulcsát adja, ezért az "első" a kulcstábla sorrendjétől függött (pl. egy külföldi, 0%-os kulcs).
     *
     * @param array<int, object|array> $rates
     * @return object|array|false
     */
    private function pickRateForOrderLocation(array $rates): object|array|false
    {
        $countryOf = static fn($rate) => strtoupper((string)(is_object($rate)
            ? ($rate->tax_rate_country ?? '')
            : ($rate['tax_rate_country'] ?? '')));

        $location = strtoupper($this->getOrderTaxLocation()['country']);
        $base = strtoupper((string)(wc_get_base_location()['country'] ?? ''));

        foreach ([$location, $base] as $country) {
            if ($country === '') {
                continue;
            }

            foreach ($rates as $rate) {
                if ($countryOf($rate) === $country) {
                    return $rate;
                }
            }
        }

        return reset($rates);
    }

    private function getVatEnumByTaxClass(string $taxClass): ?VatEnum
    {
        $taxRates = WC_Tax::get_rates_for_tax_class($taxClass);

        Billingo_Logger::info(
            'Tax rates for tax class "' . $taxClass . '": ' .
            json_encode($taxRates)
        );

        if (empty($taxRates)) {
            return null;
        }

        $firstRate = $this->pickRateForOrderLocation($taxRates);

        $rateNumber = null;

        if (is_object($firstRate) && isset($firstRate->tax_rate)) {
            $rateNumber = (float)$firstRate->tax_rate;
        } elseif (is_array($firstRate) && isset($firstRate['tax_rate'])) {
            $rateNumber = (float)$firstRate['tax_rate'];
        }

        if ($rateNumber === null) {
            Billingo_Logger::info(
                'Tax class alapján nem sikerült tax_rate értéket kiolvasni: ' .
                $taxClass
            );

            return null;
        }

        $vatEnum = VatEnum::fromNumber($rateNumber);

        if (!$vatEnum) {
            Billingo_Logger::info(
                'Tax rate nem illeszthető Billingo VatEnum értékre: ' .
                $rateNumber .
                '%'
            );

            return null;
        }

        return $vatEnum;
    }

    private function getVatEnumFromOrderProductTaxClasses(WC_Order $order): ?VatEnum
    {
        $vatValues = [];

        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $product = $item->get_product();

            if (!$product instanceof WC_Product) {
                continue;
            }

            if (
                method_exists($product, 'get_tax_status') &&
                $product->get_tax_status() !== 'taxable'
            ) {
                Billingo_Logger::info(
                    'Termék kihagyva fee ÁFA fallbackből, mert nem taxable: product_id=' .
                    $product->get_id() .
                    ', sku=' . $product->get_sku() .
                    ', tax_status=' . $product->get_tax_status()
                );

                continue;
            }

            // WooCommerce standard tax class = ''
            $taxClass = (string)$product->get_tax_class();

            Billingo_Logger::info(
                'Termék tax class vizsgálat fee fallbackhez: product_id=' .
                $product->get_id() .
                ', sku=' . $product->get_sku() .
                ', tax_class=' . $taxClass
            );

            $vatEnum = $this->getVatEnumByTaxClass($taxClass);

            if (!$vatEnum) {
                continue;
            }

            $vatValues[$vatEnum->value] = $vatEnum;
        }

        if (count($vatValues) === 1) {
            return reset($vatValues);
        }

        if (count($vatValues) > 1) {
            Billingo_Logger::info(
                'Vegyes ÁFA kulcsok vannak a rendelés termékein: ' .
                implode(', ', array_keys($vatValues)) .
                '. Mivel a getFeeVatCode() csak egy ÁFA kulcsot tud visszaadni, ' .
                'nem választunk termék alapján. Standard tax fallback következik.'
            );

            return null;
        }

        Billingo_Logger::info(
            'Fee ÁFA kulcs nem meghatározható rendelés termékek alapján, ' .
            'mert nem találtunk használható taxable terméket.'
        );

        return null;
    }

    private function getCalculatedDateForItem(string $dataName, ?array $item = null, ?string $countryCode = null): mixed
    {
        // A WooCommerce termék "Adóstátusz: Nincs" (tax_status = 'none') beállítása azt
        // jelenti, hogy a termékre soha nem számítunk fel ÁFA-t — ez FÜGGETLEN a tax_class-tól
        // (ami csak azt mondja meg, MELYIK kulcsot használjuk, HA a termék egyáltalán
        // adóköteles). A korábbi kód csak a tax_class-t nézte, a tax_status-t figyelmen kívül
        // hagyta, így egy "Nincs" adóstátuszú termék is a normál (pl. 27%-os) ÁFA-kulcsot
        // kapta a számlán.
        // A termék JELENLEGI adóstátusza csak tartalék: ha a WooCommerce a rendeléskor ténylegesen adót
        // számolt (vagy mentességet rögzített), az az irányadó — a termék utólagos átállítása nem írhatja
        // át egy már kifizetett áfás sor kódját.
        if (($dataName === 'vat' || $dataName === 'entitlement') && !is_null($item) && empty($item['recorded_vat']) && ($item['tax_status'] ?? 'taxable') !== 'taxable') {
            $exempt = $this->getExemptVatCodes();

            return $dataName === 'vat'
                ? VatEnum::from($exempt['vat'])
                : ($exempt['entitlement'] !== null ? EntitlementEnum::from($exempt['entitlement']) : null);
        }

        // A WooCommerce által a rendelés soraira ténylegesen felszámított áfa az elsődleges forrás:
        // ez figyelembe veszi a vevő szállítási országát, az adóosztályokat, az összetett adókat
        // és az adómentes sorokat is, amit a termék adóosztályából visszaszámolt kulcs nem tud.
        if (($dataName === 'vat' || $dataName === 'entitlement') && !is_null($item) && !empty($item['recorded_vat'])) {
            $recorded = $item['recorded_vat'];

            return $dataName === 'vat'
                ? VatEnum::from($recorded['vat'])
                : ($recorded['entitlement'] !== null ? EntitlementEnum::from($recorded['entitlement']) : null);
        }

        if ($dataName == 'vat' && !is_null($item)) {
            $vat = null;
            $taxRate = WC_Tax::get_rates_for_tax_class($item['tax_class']);
            $count = count($taxRate);
            if ($count === 1) {
                $vat = array_shift($taxRate)->tax_rate;
            }else{
                foreach ($taxRate as $rate) {
                    if (
                        isset($rate->tax_rate_country, $rate->tax_rate)
                        && $rate->tax_rate_country === $countryCode
                    ) {
                        $vat = $rate->tax_rate;
                        break;
                    }
                }

                // Ha nem találtunk ország szerinti áfát → fallback: első elem
                if ($vat === null) {
                    $first = reset($taxRate); // nem módosítja a tömböt
                    if ($first && isset($first->tax_rate)) {
                        $vat = $first->tax_rate;
                    }
                }
            }
            if($item['tax_class'] == 'zero-rate'){
                $vat = 0;
            }
        }
        return match ($dataName) {
            'unit' => get_option('wc_billingo_unit')
                ? __(get_option('wc_billingo_unit'), 'billingo')
                : __('db', 'billingo'),
            'vat' => isset($vat)
                ? $this->legacyVatEnum($vat, (string)($item['name'] ?? ''))
                : null,
            'unit_price_type' => UnitPriceTypeEnum::GROSS,
            'comment' => wcFlexibleIsTrue(get_option('wc_billingo_sku'))
                ? (__('Cikkszám', 'billingo') . ': ' . $this->getProductSku($item))
                : null,
            default => null,
        };
    }

    /**
     * Az adóosztály-alapú (tartalék) logikából kapott kulcs megfeleltetése a Billingo kulcsainak.
     * Ha a kulcs nem szerepel a Billingo kulcsai között, a generálás megáll (korábban csendben
     * 0% lett belőle).
     */
    private function legacyVatEnum(float|string $vat, string $name): ?VatEnum
    {
        $vatEnum = VatEnum::fromNumber((string)$vat);

        if ($vatEnum === null) {
            $this->flagVatError(sprintf(
                __('A(z) %1$s%% áfakulcs („%2$s”) nem szerepel a Billingo támogatott áfakulcsai között.', 'billingo'),
                rtrim(rtrim(number_format((float)$vat, 2, ',', ''), '0'), ','),
                $name
            ));
        }

        return $vatEnum;
    }

    private function getProductSku(?array $item): string
    {
        if (empty($item)) {
            return '';
        }

        // Variáció előnyben
        if (!empty($item['variation_id'])) {
            $product = wc_get_product($item['variation_id']);
        } else {
            $product = wc_get_product($item['product_id']);
        }
        return $product ? (string)$product->get_sku() : '';
    }

    private function getNote($order ): string
    {
        $defaultNoteOptionKey = get_option('wc_billingo_note');
        $defaultNote = '';

        if (!empty($defaultNoteOptionKey)) {
            $defaultNote = $defaultNoteOptionKey;
        }

        // Alapértelmezés szerint a boltos által beállított állandó megjegyzés mindig
        // megjelenik a számlán, még akkor is, ha a vásárló írt megjegyzést a
        // rendeléshez — ez elkerülhetővé teszi, hogy egy vásárlói megjegyzés
        // véletlenül eltüntesse a fix, gyakran jogi/adminisztratív célú szöveget.
        // Ha a boltos kifejezetten engedélyezi, a vásárlói megjegyzés felülírhatja azt.
        $customerNoteOverrideAllowed = wcFlexibleIsTrue(get_option('wc_billingo_customer_note_override_enabled'));

        $note = ($customerNoteOverrideAllowed && !empty($this->order->get_customer_note()))
            ? $this->order->get_customer_note()
            : $defaultNote;

        if (isset($this->manualIncome['note']) && !empty($this->manualIncome['note'])) {
            $note = $this->manualIncome['note'];
        }

        if (wcFlexibleIsTrue(get_option('wc_billingo_note_orderid'))) {
            $note .= "\n" . __('Megrendelés azonosító', 'billingo') . ': ' . $this->order->get_order_number();
        }

        $barrionId = $this->order->get_meta('Barion paymentId', true);

        if (wcFlexibleIsTrue(get_option('wc_billingo_note_barion'))
            && $barrionId) {
            $note .= "\n" . __('Barion tranzakció azonosító', 'billingo') . ': ' . sanitize_text_field($barrionId);
        }

        if (
            function_exists('wcFlexibleIsTrue')
            && wcFlexibleIsTrue( get_option('wc_billingo_shippingcomment') )
            && $order instanceof WC_Order
        ) {
            // Szállítási adatok biztonságosan
            $shipping = $order->get_address('shipping');

            if ( is_array($shipping) && ! empty( array_filter($shipping) ) ) {

                $postcode = (string) $order->get_shipping_postcode();
                $city     = (string) $order->get_shipping_city();
                $addr1    = (string) $order->get_shipping_address_1();
                $addr2    = (string) $order->get_shipping_address_2();

                // Összerakjuk a címet, kiszűrve az üres részeket
                $parts = array_filter([
                    $postcode,
                    $city,
                    $addr1,
                    $addr2,
                ]);

                $shipping_line = trim( preg_replace( '/\s+/', ' ', implode(' ', $parts) ) );

                if ( $shipping_line !== '' ) {
                    $note .= "\n" . __('Szállítási cím', 'billingo') . ': ' . sanitize_text_field( $shipping_line );
                }
            }
        }

        return $note;
    }

    private function selectCallback(): ?string
    {
        if (isset($this->manualIncome['invoice_type']) &&!empty($this->manualIncome['invoice_type'])){
            // Kifejezett, kézi bizonylattípus-választás (admin felület) mindig elsőbbséget élvez.
            $settingsValue = $this->manualIncome['invoice_type'];
        } elseif (($productOverride = $this->getProductDocumentTypeOverride()) !== null) {
            // Ha a rendelésben van legalább egy, a termékoldalon bizonylattípus-felülírásra
            // jelölt tétel, a teljes rendeléshez azt a típust állítjuk ki a normál auto/kézi
            // beállítás helyett. Csak az ezt a terméket tartalmazó rendeléseket érinti, a
            // többinél nincs változás.
            $settingsValue = $productOverride;
        } else {
            $settingsValue = wcFlexibleIsTrue($this->order->get_meta('_is_manual', true))
                ? get_option('wc_billingo_manual_type')
                : get_option('wc_billingo_auto');

        }


        return match ($settingsValue) {
            'invoice' => 'getInvoice',
            'proforma' => 'getProforma',
            'draft' => 'getDraft',
            'advance' => 'getAdvance',
            default => null,
        };

    }

    /**
     * Megvizsgálja, hogy a rendelés tartalmaz-e legalább egy olyan terméket, amelynél a
     * termékszerkesztő "Számlázás" fülén bizonylattípus-felülírás van beállítva (számla/
     * díjbekérő/piszkozat/előlegszámla), és ha igen, visszaadja az első ilyen felülírás
     * értékét. Ha a rendelésben több termék is felülírással rendelkezik, az első (tétel-
     * sorrend szerinti) találat érvényesül.
     */
    private function getProductDocumentTypeOverride(): ?string
    {
        $validOverrides = ['invoice', 'proforma', 'draft', TypeEnum::ADVANCE->value];

        foreach ($this->order->get_items() as $item) {
            $product = $item->get_product();

            if (!$product) {
                continue;
            }

            // A beállítást a termékszerkesztő a főterméken menti: variációs termék soránál a get_product() a
            // variációt adja, annak azonosítóján nincs meta — ezért a variáció után a főterméket is megnézzük.
            $productIds = array_unique(array_filter([
                (int)$product->get_id(),
                method_exists($item, 'get_product_id') ? (int)$item->get_product_id() : 0,
            ]));

            foreach ($productIds as $productId) {
                $override = get_post_meta($productId, '_billingo_document_type_override', true);

                if (in_array($override, $validOverrides, true)) {
                    return $override;
                }
            }
        }

        return null;
    }

    private function isForbidden(): bool
    {
        $isForbidden = false;

        // has child orders but is disabled
        if (wcFlexibleIsTrue(get_option('wc_billingo_block_child_orders')) && $this->order->get_parent_id() != 0) {

            $this->lastError = __('A rendelés alrendelés, és az alrendelésekhez tartozó számlázás ki van kapcsolva a beállításokban.', 'billingo');
            Billingo_Logger::error('Document creation exits: Child orders are disabled');
            $isForbidden = true;
            $this->skippedByDesign = true;
        }

        // 0 Ft végösszegű rendelés, és a boltos kikapcsolta ezekre a számlázást
        if (wcFlexibleIsTrue(get_option('wc_billingo_skip_zero_total')) && (float)$this->order->get_total() === 0.0) {

            $this->lastError = __('A rendelés végösszege 0 Ft, és a 0 Ft-os rendelésekhez tartozó számlázás ki van kapcsolva a beállításokban.', 'billingo');
            Billingo_Logger::info('Document creation exits: Order total is 0, and zero-total invoicing is disabled.');
            $isForbidden = true;
            $this->skippedByDesign = true;
        }

        return $isForbidden;
    }


    /**
     * Áfa felülírás
     * 0%-os felülírás esetén jogcímet kell küldeni nem a százalékot a vat field-ben is.
     * @param array $items
     * @return array
     */
    /**
     * A rendelés adózási helye a WooCommerce "adó számítása a következő cím alapján" beállítása szerint
     * (szállítási / számlázási cím, vagy a bolt székhelye). Ha a kért cím országa üres, a másik címre, végül a
     * bolt székhelyére esik vissza.
     *
     * @return array{country: string, state: string, postcode: string, city: string}
     */
    private function getOrderTaxLocation(): array
    {
        $base = wc_get_base_location();
        $location = [
            'country'  => (string)($base['country'] ?? ''),
            'state'    => (string)($base['state'] ?? ''),
            'postcode' => '',
            'city'     => '',
        ];

        $basedOn = get_option('woocommerce_tax_based_on', 'shipping');

        if ($basedOn === 'base' || !($this->order instanceof WC_Order)) {
            return $location;
        }

        $types = $basedOn === 'billing' ? ['billing', 'shipping'] : ['shipping', 'billing'];

        foreach ($types as $type) {
            $country = strtoupper(trim((string)$this->order->{"get_{$type}_country"}()));

            if ($country !== '') {
                return [
                    'country'  => $country,
                    'state'    => (string)$this->order->{"get_{$type}_state"}(),
                    'postcode' => (string)$this->order->{"get_{$type}_postcode"}(),
                    'city'     => (string)$this->order->{"get_{$type}_city"}(),
                ];
            }
        }

        return $location;
    }

    /**
     * A szállítási tételre (és a szállítási kuponra) is kiterjed-e az ÁFA felülírás: csak akkor, ha maga a felülírás is
     * be van kapcsolva. A "szállításra is" kapcsoló kikapcsolt felülírás mellett is megmaradhat bekapcsolva, ezért
     * önmagában nem dönthet (korábban ilyenkor a szállítás 0%-os kulccsal került a számlára).
     */
    private function shouldOverrideCarrierTax(): bool
    {
        return wcFlexibleIsTrue(get_option('wc_billingo_tax_override'))
            && wcFlexibleIsTrue(get_option('wc_billingo_tax_override_include_carrier'));
    }

    private function overrideTax(array|DocumentProductData $items): array|DocumentProductData
    {
        if(is_array($items)){
            foreach ($items as $item) {

                $this->overrideTaxOnSingleItem($item);
            }
        }else{

            $this->overrideTaxOnSingleItem($items);
        }
        return $items;
    }

    private function overrideTaxOnSingleItem(DocumentProductData $item): DocumentProductData
    {
        // A felület alapértelmezése az "ÁFA felülírás" (1); a mentetlen (false) beállítás a korábbi == 0
        // összehasonlításban tévesen 0%-os módnak számított.
        $typeisZero = (int)get_option('wc_billingo_tax_override_choice', 1) === 0;
        Billingo_Logger::info('=== Áfa felülírás a tételen: ' . $item->name . '===');
        if($typeisZero){
            Billingo_Logger::info('Áfa felülírás beállítása 0%-os esetén... ' . $item->name);
        }else{
            Billingo_Logger::info('Áfa felülírás beállítása nem 0%-os esetén... ' . $item->name);
        }

        $entitlemet = $typeisZero
            ? get_option('wc_billingo_tax_override_zero_entitlements')
            : get_option('wc_billingo_tax_override_entitlements');
        Billingo_Logger::info('Áfa felülírás jogcím: ' . $entitlemet);

        if (!$typeisZero) {
            $vatEnum = VatEnum::tryFrom(get_option('wc_billingo_tax_override_value'));

            if ($vatEnum === null) {
                Billingo_Logger::error('Áfa felülírás érték nincs beállítva vagy érvénytelen ezen a tételen, a generálás megszakad: ' . $item->name);
                $this->taxOverrideFailed = true;

                return $item;
            }

            $value = $vatEnum->value;
        } else {
            $value = $entitlemet;
        }
        Billingo_Logger::info('Áfa felülírás ÁFA ÉRTÉKE: ' . $value);

        if (!$typeisZero) {
            $item->vat = $value;
            $item->entitlement = $entitlemet;
        } elseif (VatEnum::tryFrom((string)$value) !== null) {
            // A jogcím egyben érvényes áfakód is (pl. AAM, TAM): a korábbi viselkedés. A jogcímet is
            // ehhez igazítjuk, hogy egy korábbi (más) jogcím ne maradjon a tételen (pl. TAM + EAM).
            $item->vat = $value;
            $item->entitlement = $this->entitlementForVatCode((string)$value);
        } else {
            // A jogcím (pl. KBAET, EAM) nem szerepel a VatEnum értékei között, ezért nem küldhető
            // áfakódként: AM áfakódot küldünk, a jogcím pedig külön mezőben megy.
            $item->vat = VatEnum::AM->value;
            $item->entitlement = $value;
        }
        Billingo_Logger::info('=== ===');
        return $item;
    }

    private function shouldSendEmail(string $type): bool
    {
        $wcEmailId = match($type){
            'invoice' => 'wc_billingo_email',
            'proforma' => 'wc_billingo_proforma_email',
            'advance' => 'wc_billingo_advance_email',
            'cancellation' => 'wc_billingo_storno_email',
            default => false,
        };

        return in_array(get_option($wcEmailId), ['billingo', 'both']);
    }

    private function hasEraseCode(WC_Order_Item $item): bool
    {
        $optionKey = get_option('wc_billingo_is_generate_erase_code', false);
        if (empty($optionKey)) {
            return false;
        }

        $fieldName = 'pa_' . $optionKey;
        $product   = wc_get_product($item->get_product_id());

        return $product && $product->get_attribute($fieldName) === '1';
    }

    /**
     * Megállapítja, hogy egy kupon a szállításhoz tartozik-e
     * (WooCommerce Extended Coupon Features FREE plugin alapján)
     *
     * @param string $couponCode A kupon kódja
     * @return bool True, ha szállítási kupon, false egyébként
     */
    /**
     * A kuponok teljes (bruttó) kedvezményét a WooCommerce már a termék-tételekre számolta-e: a termék sorok
     * részösszeg és végösszeg közti különbsége (adóval) eléri a kuponok összesített kedvezményét. Ilyenkor a számlán
     * a termék-tételek "- kedvezmény" sorai már levonják, további (pl. szállítási kupon) sor duplán vonná le.
     */
    private function isCouponDiscountAlreadyOnItems(): bool
    {
        $couponTotal = 0.0;

        foreach ($this->order->get_items('coupon') as $couponItem) {
            $couponTotal += abs((float)$couponItem->get_discount());
            $couponTotal += method_exists($couponItem, 'get_discount_tax') ? abs((float)$couponItem->get_discount_tax()) : 0.0;
        }

        $itemTotal = 0.0;

        foreach ($this->order->get_items('line_item') as $lineItem) {
            $itemTotal += ((float)$lineItem->get_subtotal() + (float)$lineItem->get_subtotal_tax())
                - ((float)$lineItem->get_total() + (float)$lineItem->get_total_tax());
        }

        return $couponTotal > 0 && $itemTotal + 0.5 >= $couponTotal;
    }

    private function isCouponForShipping(string $couponCode): bool
    {
        // Lekérjük a kupon objektumot
        $coupon = new \WC_Coupon($couponCode);

        if (!$coupon->get_id()) {
            return false;
        }

        // Ellenőrizzük a WooCommerce Extended Coupon Features plugin meta értékét
        $shippingRestrictions = get_post_meta($coupon->get_id(), '_wjecf_shipping_restrictions', true);

        if (empty($shippingRestrictions)) {
            return false;
        }

        // Ha string formátumban van, deserialize-áljuk
        if (is_string($shippingRestrictions)) {
            $shippingRestrictions = maybe_unserialize($shippingRestrictions);
        }

        // Ha nem tömb, akkor nem tudunk mit kezdeni vele
        if (!is_array($shippingRestrictions)) {
            return false;
        }

        // Ellenőrizzük, hogy van-e benne "method:" prefix
        // Ez jelzi, hogy szállítási módszerhez van kötve a kupon
        foreach ($shippingRestrictions as $restriction) {
            if (is_string($restriction) && strpos($restriction, 'method:') === 0) {
                Billingo_Logger::info("Kupon '{$couponCode}' szállítási kuponként azonosítva: {$restriction}");
                return true;
            }
        }

        return false;
    }

    /**
     * Szállítási kupon ÁFA kulcsának meghatározása
     * A rendelés szállítási módszeréből próbálja meghatározni az ÁFA kulcsot
     *
     * @return array{vat: string, entitlement: ?string}
     */
    private function getShippingCouponVatData(): array
    {
        $shippingMethods = $this->order->get_shipping_methods();

        if (!empty($shippingMethods)) {
            // Az első szállítási módszer áfakulcsát (vagy adómentességét) használjuk
            $firstShippingMethod = reset($shippingMethods);
            return $this->getShippingVatData($firstShippingMethod);
        }

        // Ha nincs szállítási módszer, akkor a standard 27%-ot használjuk
        return ['vat' => VatEnum::PERCENT_27->value, 'entitlement' => null];
    }

    /**
     * Lekéri a termék regular price-ját az order item meta-ból vagy a termékből
     *
     * @param WC_Order_Item $item Order item objektum
     * @param WC_Product|null $product Termék objektum (opcionális)
     * @return float Regular price
     */
    private function getRegularPriceFromItemMeta($item, $product = null): float
    {
        // Először próbáljuk meg lekérni az elmentett regular price-t az order item meta-ból
        $savedRegularPrice = wc_get_order_item_meta($item->get_id(), '_wc_billingo_product_full_price_without_sale', true);

        if (!empty($savedRegularPrice)) {
            Billingo_Logger::info('Using saved regular price from order item meta: ' . $savedRegularPrice);
            return floatval($savedRegularPrice);
        }

        Billingo_Logger::warning('Could not determine regular price, returning 0');
        return 0;
    }

    /**
     * Lekéri a termék akciós árát az order item meta-ból vagy a termékből
     *
     * @param WC_Order_Item $item Order item objektum
     * @return float Sale price
     */
    private function getSalePriceFromItemMeta($item): float
    {
        $savedSalePrice = wc_get_order_item_meta($item->get_id(), '_wc_billingo_product_full_price_with_sale', true);
        //a subtotalt kell visszadni, ha nincs savedSalePrice mert az a tényleges ár, ha akciós.
        if (!empty($savedSalePrice)) {
            return floatval($savedSalePrice);
        } else {
            Billingo_Logger::info('getSalePriceFromItemMeta: Nem található a meta tag-ben a termék akciós ára');
            // A mentett ár hiányában a tétel nettó egységára (bruttó összehasonlításhoz az adót is hozzá kell adni).
            $quantity = (float)$item->get_quantity();

            return $quantity > 0
                ? ($item->get_subtotal() + $item->get_subtotal_tax()) / $quantity
                : 0.0;
        }
    }

    public function getCurrencyRate($from, $to)
    {
        if ($from == $to) {
            return 1.0;
        }

        $params = ['from' => $from, 'to' => $to];
        Billingo_Logger::info('getCurrencyRate request params: ' . json_encode($params));

        try {
            // Try using the query approach
            $currencyApi = new CurrencyApi();
            $result = $currencyApi->query()
                ->where('from', $from)
                ->where('to', $to)
                ->getData();

            Billingo_Logger::info('getCurrencyRate query result type: ' . gettype($result));

            if ($result && $result->conversation_rate) {
                Billingo_Logger::info('getCurrencyRate SUCCESS via query - Rate: ' . $result->conversation_rate);
                return $result->conversation_rate;
            }

            // Fallback to direct API call
            $apiResponse = $currencyApi->getConversationRate($params);
            $response = $apiResponse->getResponse();

            Billingo_Logger::info('getCurrencyRate response status: ' . $response->getStatusCode());

            if ($response->getStatusCode() == 200) {
                $data = $response->getData();
                Billingo_Logger::info('getCurrencyRate data type: ' . gettype($data));

                if ($data && $data->conversation_rate) {
                    $rate = $data->conversation_rate;
                    Billingo_Logger::info('getCurrencyRate SUCCESS - Rate: ' . $rate);
                    return $rate;
                }
            }
        } catch (\Exception $e) {
            Billingo_Logger::error('getCurrencyRate exception: ' . $e->getMessage());
        }

        // Nem HUF devizánál TILOS 1.0-s árfolyamra visszaesni: az a számlán hibás HUF-értéket és
        // ÁFA-alapot adna. A hívó (resolveConversionRate) ilyenkor leállítja a generálást.
        Billingo_Logger::error('getCurrencyRate FAILED - a Billingo nem adott érvényes árfolyamot (' . $from . ' -> ' . $to . ')');
        return null;
    }

    /**
     * A "wc_billingo_invoice_lang_by_currency_map" beállítás soronként "DEVIZA=nyelvkód"
     * formátumú párokat tartalmaz (pl. "EUR=de"). Visszaadja a rendelés devizájához tartozó
     * nyelvet, ha van érvényes egyezés, egyébként null-t (ilyenkor a hívó a normál
     * nyelv-meghatározási logikára esik vissza).
     */
    private function resolveLanguageFromCurrency(string $currency, array $validEnums): ?string
    {
        $map = get_option('wc_billingo_invoice_lang_by_currency_map', '');

        if (empty($map) || empty($currency)) {
            return null;
        }

        foreach (preg_split('/\r\n|\r|\n/', $map) as $line) {
            $line = trim($line);

            if ($line === '' || !str_contains($line, '=')) {
                continue;
            }

            [$mappedCurrency, $mappedLang] = array_map('trim', explode('=', $line, 2));
            $mappedLang = strtolower(substr($mappedLang, 0, 2));

            if (strcasecmp($mappedCurrency, $currency) === 0 && in_array($mappedLang, $validEnums, true)) {
                return $mappedLang;
            }
        }

        return null;
    }

    function get_order_language(WC_Order $order) : string
    {
        // --- ENUM lista ---
        $validEnums = array_column(LanguageEnum::cases(), 'value');

        // --- Admin default (mindig legyen érték) ---
        $default = get_option('wc_billingo_invoice_lang') ?: LanguageEnum::HU->value;
        $default = strtolower(substr($default, 0, 2));

        // Admin default is valid? Ha nem → HU fallback
        if (!in_array($default, $validEnums, true)) {
            $default = LanguageEnum::HU->value;
        }

        // Order type check
        if (!$order instanceof WC_Order) {
            return $default;
        }

        // Devizától függő számlanyelv engedélyezve?
        if (wcFlexibleIsTrue(get_option('wc_billingo_invoice_lang_by_currency_enabled'))) {
            $currencyLang = $this->resolveLanguageFromCurrency($order->get_currency(), $validEnums);

            if (!is_null($currencyLang)) {
                return $currencyLang;
            }
        }

        // WPML alapú nyelvhasználat engedélyezve?
        if (wcFlexibleIsTrue(get_option('wc_billingo_invoice_lang_wpml'))) {

            // Források sorrendben
            $possibleSources = [
                $order->get_meta('wpml_order_language', true),
                $order->get_meta('wpml_language', true),
                function_exists('pll_get_post_language')
                    ? pll_get_post_language($order->get_id(), 'slug')
                    : null,
                $order->get_meta('trp_language', true),
                substr((string)$order->get_meta('_locale', true), 0, 2),
            ];

            foreach ($possibleSources as $lang) {
                if (is_string($lang) && $lang !== '') {

                    $lang = strtolower(substr($lang, 0, 2));

                    // ENUM validáció – CSAK ezeket fogadjuk el
                    if (in_array($lang, $validEnums, true)) {
                        return $lang;
                    }
                }
            }
        }

        // Ha nincs érvényes találat → admin default
        return $default;
    }


}

