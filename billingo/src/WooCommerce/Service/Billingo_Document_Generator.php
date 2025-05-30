<?php

namespace App\Billingo\WooCommerce\Service;

use App\Billingo\Enums\Document\TypeEnum;
use App\Billingo\Enums\PaymentMethodEnum;
use App\Billingo\Enums\UnitPriceTypeEnum;
use App\Billingo\Enums\VatEnum;
use App\Billingo\Exceptions\BadContentException;
use App\Billingo\Models\Document\DocumentInsert;
use App\Billingo\Models\Document\DocumentProductData;
use App\Billingo\Models\Partner\Partner;
use App\Billingo\Service\BillingoClient;
use Symfony\Component\HttpFoundation\Response;
use App\Billingo\WooCommerce\Service\Billingo_Product_Sync;
use App\Billingo\Enums\CurrencyEnum;
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

    public function __construct(int $orderId, private readonly ?array $manualIncome = null)
    {
        $this->client = new BillingoClient(get_option('wc_billingo_api_key', ''));
        $this->order = wc_get_order($orderId);
        $this->documentTypeCallback = $this->selectCallback();
    }

    public function get(): ?DocumentInsert
    {
        if ($this->isForbidden()) {

            return null;
        }

        $callback = $this->documentTypeCallback;

        return is_null($callback) ? null : $this->$callback();
    }

    public function getInvoice(): ?DocumentInsert
    {
        $invoice = $this->make(TypeEnum::INVOICE);

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

    private function make(TypeEnum $type): ?DocumentInsert
    {
        Billingo_Logger::info('Order ID: ' . $this->order->get_id());
        
        $this->currentDocumentType = $type->value;

        $this->collectDocumentData();

        $this->documentData['type'] = $type->value;

        $document = new DocumentInsert($this->documentData);

        if ($document->hasError()) {

            $errors = $document->getErrors();
            Billingo_Logger::error(ucfirst($type->value) . " generation: FAIL {$errors->getType()->value} "
                . (empty($errors->getValues()) ? '' : json_encode($errors->getValues())));

            return null;
        } else {

            Billingo_Logger::info(ucfirst($type->value) . ' generation: SUCCESSFUL');
        }
        Billingo_Logger::info('Tax override: ' . get_option('wc_billingo_tax_override'));
        if (get_option('wc_billingo_tax_override')) {
            Billingo_Logger::info('Áfa felülírási igény');
            $document->items = $this->overrideTax($document->items);
        }

        if($document && $this->shouldSendEmail($document->type)){

            $document->settings->should_send_email = true;
            Billingo_Logger::info('Email send by Billingo');
        }
        Billingo_Logger::info('Document: ' . json_encode($document));
        return $document;
    }

    private function collectDocumentData(): void
    {
        $paymentMethod = $this->resolvePaymentMethod();
        $paidType = $this->resolvePaidType();
        Billingo_Logger::info('Payment Currency: ' . $this->order->get_currency());
        
        $deadline = isset($this->manualIncome['deadline'])
            ? (int)$this->manualIncome['deadline']
            : (int)get_option("wc_billingo_paymentdue_{$this->order->get_payment_method()}");
        $language = wcFlexibleIsTrue(get_option('wc_billingo_invoice_lang_wpml'))
        && !empty(get_post_meta($this->order->get_id(), 'wpml_order_language', true))
            ? get_post_meta($this->order->get_id(), 'wpml_order_language', true)
            : get_option('wc_billingo_invoice_lang');

        $currency = $this->order->get_currency() ?: 'HUF';

        $document = [
            'partner_id' => $this->findOrCreatePartner($this->getPartnerName()),
            'block_id' => (int)get_option('wc_billingo_invoice_block'),
            'fulfillment_date' => isset($this->manualIncome['completed'])
                ? $this->manualIncome['completed']
                : wp_date('Y-m-d', time()),
            'due_date' => wp_date('Y-m-d', strtotime('+' . $deadline . ' days')),
            'payment_method' => $paymentMethod,
            'paid' => $paidType,
            'language' => $language,
            'currency' => $currency,
            'conversion_rate' => 1.0,
            'electronic' => wcFlexibleIsTrue(get_option('wc_billingo_electronic')),
            'items' => $this->createProductItems(),
            'comment' => $this->getNote(),
            'settings' => [
                'round' => get_option('wc_billingo_invoice_round'),
                'without_financial_fulfillment' => wcFlexibleIsTrue(get_option('mark_paid_without_financial_fulfillment')),
                'should_send_email' => false,
            ],
        ];

        $this->documentData = $document;
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
        $descriptions = [
            'name' => $name,
            'address' => [
                'country_code' => $this->order->get_billing_country(),
                'post_code' => $this->order->get_billing_postcode(),
                'city' => $this->order->get_billing_city(),
                'address' => $this->order->get_billing_address_1(),
            ],
        ];

        $foundPartner = $this->findPartner($descriptions);

        if (!is_null($foundPartner)) {

            return $foundPartner->id;
        }

        $descriptions['emails'] = [$this->order->get_billing_email()];

        try {
            $createdPartner = $this->client
                ->partner()
                ->create($descriptions)
                ->getResponse();

            if ($createdPartner->getStatusCode() === Response::HTTP_CREATED) {

                $createdPartnerId = $createdPartner->getData()->id;

                Billingo_Logger::info("Partner created with ID: {$createdPartnerId}");
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

    private function createProductItems(): array
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
        
        // Termékek és termék-kedvezmények hozzáadása
        foreach ($items as $item) {
            $itemObject = $item;
            $itemData = $item->get_data();
            $product = $item->get_product();
            
            if ($shouldSyncProducts && isset($productSync) && $product) {
                try {
                    $productSync->syncProduct($product, $itemData);
                } catch (\Exception $e) {
                    Billingo_Logger::error('Hiba a termék szinkronizálásakor: ' . $e->getMessage());
                }
            }
            
            // Az EREDETI árat használjuk, nem az akciós árat
            $unitPrice = $product && method_exists($product, 'get_regular_price') && $product->get_regular_price() 
                ? floatval($product->get_regular_price()) 
                : (isset($itemData['total']) && isset($itemData['quantity']) && $itemData['quantity'] > 0 
                    ? $itemData['total'] / $itemData['quantity'] 
                    : 0);
            
            // Dokumentum elem létrehozása
            try {
                $originalItem = new DocumentProductData([
                    'name' => $itemData['name'] ?? 'Termék',
                    'quantity' => $itemData['quantity'] ?? 1,
                    'unit_price' => $unitPrice,
                    'unit_price_type' => $this->getCalculatedDateForItem('unit_price_type')->value,
                    'unit' => $this->getCalculatedDateForItem('unit'),
                    'vat' => $this->getCalculatedDateForItem('vat', $itemData)->value ?? '0%',
                    'comment' => $this->getCalculatedDateForItem('comment', $itemData),
                    'entitlement' => $this->getCalculatedDateForItem('entitlement', $itemData)?->value,
                    'sku' => !empty($this->getProductSku($itemData)) ? $this->getProductSku($itemData) : null,
                    'is_generate_erase_code' => $this->hasEraseCode($itemObject),
                ]);
                $productItems[] = $originalItem;
            } catch (\Exception $e) {
                Billingo_Logger::error('Hiba a dokumentum elem létrehozásakor: ' . $e->getMessage());
                // Folytatjuk a következő elemmel
                continue;
            }
            
            // Termék akciós kedvezmény hozzáadása - csak akkor, ha tényleg akciós a termék
            if ($product && method_exists($product, 'is_on_sale') && $product->is_on_sale() && $product->get_sale_price()) {
                $regularPrice = floatval($product->get_regular_price());
                $salePrice = floatval($product->get_sale_price());
                
                if ($regularPrice > $salePrice) {
                    $vatObject = $this->getCalculatedDateForItem('vat', $itemData)->value ?? '0%';
                    $vatRate = $this->getVatRateFromCode($vatObject);
                    $grossDiscount = ($regularPrice - $salePrice) * ($itemData['quantity'] ?? 1); //ezt a termékkedvezményt csak azért vesszük bruttónak, mert mindent bruttóként kezelünk

                    Billingo_Logger::info('Kedvezmény tétel hozzáadása bruttó értékkel: ' . $grossDiscount);

                    // Kedvezmény tétel hozzáadása bruttó értékkel
                    $discountItem = new DocumentProductData([
                        'name' => __('Kedvezmény - ', 'billingo') . ($itemData['name'] ?? 'Termék'),
                        'quantity' => 1,
                        'unit_price' => -$grossDiscount,
                        'unit_price_type' => UnitPriceTypeEnum::GROSS->value,
                        'unit' => $this->getCalculatedDateForItem('unit'),
                        'vat' => $this->getCalculatedDateForItem('vat', $itemData)->value ?? '0%',
                    ]);
                    
                    $productItems[] = $discountItem;
                }
            }
        }
        
        // Kupon kedvezmény hozzáadása 
        $totalDiscount = $this->order->get_total_discount();
        
        if ($totalDiscount > 0) {
            // Ellenőrizzük, hogy a termék-kedvezményeken felül van-e még kupon kedvezmény
            $productDiscountTotal = 0;
            foreach ($productItems as $item) {
                if (strpos($item->name ?? '', __('Kedvezmény - ', 'billingo')) === 0 && $item->unit_price < 0) {
                    $productDiscountTotal += abs($item->unit_price);
                }
            }
            
            // Ha a kupon kedvezmény nagyobb, mint amit már hozzáadtunk a termékkedvezményekkel,
            // akkor a különbséget külön tételként hozzáadjuk
            $remainingDiscount = $totalDiscount - $productDiscountTotal;
            if ($remainingDiscount > 0.5) { // Kis margót hagyunk a kerekítési hibák miatt
                // Kupon kedvezmény esetén az első termék ÁFA kulcsát használjuk
                $firstItem = reset($items);
                $firstItemData = $firstItem ? $firstItem->get_data() : null;
                $vatCode = $firstItemData ? $this->getCalculatedDateForItem('vat', $firstItemData)->value : VatEnum::PERCENT_27->value;
                $vatRate = $this->getVatRateFromCode($vatCode);
                
                // A kedvezmény nettó, ezért bruttósítjuk
                $grossRemainingDiscount = $remainingDiscount * (1 + ($vatRate / 100));
                
                $discountItem = new DocumentProductData([
                    'name' => __('Kupon kedvezmény', 'billingo'),
                    'quantity' => 1,
                    'unit_price' => -$grossRemainingDiscount,
                    'unit_price_type' => $this->getCalculatedDateForItem('unit_price_type'),
                    'unit' => $this->getCalculatedDateForItem('unit'),
                    'vat' => $vatCode
                ]);
                
                $productItems[] = $discountItem;
            }
        }
        
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
            
            foreach ($shippingMethods as $shippingMethod) {
                $shippingMethodTitle = $shippingMethod->get_method_title();
                $shippingTotal = floatval($shippingMethod->get_total());
                
                if ($shippingTotal > 0) {
                    // Szállítási tétel létrehozása a tényleges összeggel
                    $shippingItem = new DocumentProductData([
                        'name' => !empty($shippingMethodTitle) 
                            ? __('Szállítás - ', 'billingo') . $shippingMethodTitle 
                            : __('Szállítás', 'billingo'),
                        'quantity' => 1,
                        'unit_price' => $shippingTotal,
                        'unit_price_type' => $this->getCalculatedDateForItem('unit_price_type')->value,
                        'unit' => $this->getCalculatedDateForItem('unit'),
                        'vat' => $this->getShippingVatCode($shippingMethod)->value,
                        'comment' => ''
                    ]);
                    
                    $productItems[] = $shippingItem;
                    Billingo_Logger::info('Szállítási tétel hozzáadva: ' . $shippingMethodTitle . ' (' . $shippingTotal . ' ' . $this->order->get_currency() . ')');
                }
            }
        }
        // Ha nincs szállítási költség, de a "mindig látszódjon" beállítás aktív, akkor 0 összegű tételt adunk hozzá
        if (wcFlexibleIsTrue(get_option('wc_billingo_always_add_carrier'))) {
            Billingo_Logger::info('Nincs szállítási költség, de a "Szállító mindig látszódjon" beállítás aktív - 0 összegű tétel hozzáadása');
            
            if (!empty($shippingMethods)) {
                foreach ($shippingMethods as $shippingMethod) {
                    $shippingMethodTitle = $shippingMethod->get_method_title();
                    
                    $shippingItem = new DocumentProductData([
                        'name' => !empty($shippingMethodTitle) 
                            ? __('Szállítás - ', 'billingo') . $shippingMethodTitle 
                            : __('Szállítás', 'billingo'),
                        'quantity' => 1,
                        'unit_price' => 0,
                        'unit_price_type' => $this->getCalculatedDateForItem('unit_price_type')->value,
                        'unit' => $this->getCalculatedDateForItem('unit'),
                        'vat' => $this->getShippingVatCode($shippingMethod)->value,
                        'comment' => ''
                    ]);
                    
                    $productItems[] = $shippingItem;
                    Billingo_Logger::info('Ingyenes szállítási tétel hozzáadva: ' . $shippingMethodTitle . ' (0 összegű)');
                    
                    break;
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
        // Ha van szállítási módszer, próbáljuk meg lekérni az ÁFA kulcsot
        if ($shippingMethod && method_exists($shippingMethod, 'get_taxes')) {
            $taxes = $shippingMethod->get_taxes();
            if (!empty($taxes)) {
                // Az első ÁFA kulcsot használjuk
                $taxRateId = array_key_first($taxes);
                if ($taxRateId) {
                    $taxRate = WC_Tax::_get_tax_rate($taxRateId);
                    if ($taxRate && isset($taxRate['tax_rate'])) {
                        $vatEnum = VatEnum::fromNumber($taxRate['tax_rate']);
                        if ($vatEnum) {
                            return $vatEnum;
                        }
                    }
                }
            }
        }
        
        // Ha nem sikerült meghatározni a szállítási ÁFA kulcsot, 
        // akkor az alapértelmezett 27%-ot használjuk (magyar standard)
        return VatEnum::PERCENT_27;
    }
    
    /**
     * Visszaadja az ÁFA kulcs százalékos értékét a kód alapján
     */
    private function getVatRateFromCode(string $vatCode): float
    {
        // Alapértelmezett érték: 27%
        $defaultRate = 27.0;
        
        // Eltávolítjuk a '%' karaktert és egyéb nem numerikus karaktereket
        $vatRate = preg_replace('/[^0-9\.]/', '', $vatCode);
        return !empty($vatRate) ? floatval($vatRate) : $defaultRate;
    }

    private function getCalculatedDateForItem(string $dataName, array $item = null): mixed
    {
        if ($dataName == 'vat' && !is_null($item)) {
            $taxRate = WC_Tax::get_rates_for_tax_class($item['tax_class']);
            $vat = array_shift($taxRate)->tax_rate;
        }
        return match ($dataName) {
            'unit' => get_option('wc_billingo_unit')
                ? __(get_option('wc_billingo_unit'), 'billingo')
                : __('db', 'billingo'),
            'vat' => isset($vat)
                ? VatEnum::fromNumber($vat)
                : null,
            'unit_price_type' => UnitPriceTypeEnum::GROSS,
            'comment' => wcFlexibleIsTrue(get_option('wc_billingo_sku'))
                ? (__('Cikkszám', 'billingo') . ': ' . $this->getProductSku($item))
                : null,
            default => null,
        };
    }

    private function getProductSku(?array $item): string
    {
        if (is_null($item)) {

            return '';
        }

        return (new WC_Product($item['product_id']))->get_sku();
    }

    private function getNote(): string
    {
        $note = empty($this->order->get_customer_note())
            ? get_option(get_option('wc_billingo_note'))
            : $this->order->get_customer_note();

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

        return $note;
    }

    private function selectCallback(): ?string
    {
        if (isset($this->manualIncome['invoice_type']) &&!empty($this->manualIncome['invoice_type'])){
            $settingsValue = $this->manualIncome['invoice_type'];
        }else {
            $settingsValue = wcFlexibleIsTrue(get_post_meta($this->order->get_id(), '_is_manual', true))
                ? get_option('wc_billingo_manual_type')
                : (
                get_option('wc_billingo_proforma_' . $this->order->get_payment_method()) == 1
                    ? 'proforma'
                    : get_option('wc_billingo_auto')
                );
        }


        return match ($settingsValue) {
            'invoice' => 'getInvoice',
            'proforma' => 'getProforma',
            'draft' => 'getDraft',
            default => null,
        };

    }

    private function isForbidden(): bool
    {
        $isForbidden = false;

        // has child orders but is disabled
        if (wcFlexibleIsTrue(get_option('wc_billingo_block_child_orders')) && $this->order->get_parent_id() != 0) {

            Billingo_Logger::error('Document creation exits: Child orders are disabled');
            $isForbidden = true;
        }

        return $isForbidden;
    }


    /**
     * Áfa felülírás
     * 0%-os felülírás esetén jogcímet kell küldeni nem a százalékot a vat field-ben is.
     * @param array $items
     * @return array
     */
    private function overrideTax(array $items): array
    {
        Billingo_Logger::info('Áfa felülírás beállítása...');
        $typeisZero = get_option('wc_billingo_tax_override_choice') == 0;
        Billingo_Logger::info('Áfa felülírás beállítása: ' . $typeisZero);

        $entitlemet = $typeisZero
            ? get_option('wc_billingo_tax_override_zero_entitlements')
            : get_option('wc_billingo_tax_override_entitlements');
        Billingo_Logger::info('Áfa felülírás entitlement: ' . $entitlemet);
        $value = $typeisZero
            ? $entitlemet
            : VatEnum::from(get_option('wc_billingo_tax_override_value'))->value;
        Billingo_Logger::info('Áfa felülírás VAT ÉRTÉKE: ' . $value);

        //todo megállapítani hogy az érkező tétel szállítási költség-e és ha igen akkor megvizsgálni hogy a wc_billingo_tax_override_include_carrier beállítás aktív-e és ha szükséges akkor a szállítási költség áfáját is írjuk felül

        if (!$typeisZero) {
            foreach ($items as $item) {
                $item->vat = $value;
                $item->entitlement = $entitlemet;
            }
        } else {
            foreach ($items as $item) {

                    $item->vat = $value;
        }
    }

        return $items;
    }

    private function shouldSendEmail(string $type): bool
    {
        $wcEmailId = match($type){
            'invoice' => 'wc_billingo_email',
            'proforma' => 'wc_billingo_proforma_email',
            'cancellation' => 'wc_billingo_storno_email',
            default => false,
        };

        return in_array(get_option($wcEmailId), ['billingo', 'both']);
    }

    private function hasEraseCode(WC_Order_Item $item): bool
    {
        $fieldName = get_option('wc_billingo_is_generate_erase_code', false);
        if (!$fieldName) {

            return false;
        }

        $productId = $item->get_product_id();

        return get_post_meta($productId, '_product_attributes',true)[$fieldName]['value'] ?? false;
    }

}

