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

        $document = [
            'partner_id' => $this->findOrCreatePartner($this->getPartnerName()),
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
            'conversion_rate' => $currency === 'HUF' ? 1.0 : $this->getCurrencyRate($currency, 'HUF'),
            'electronic' => wcFlexibleIsTrue(get_option('wc_billingo_electronic')),
            'items' => $this->createProductItems(),
            'comment' => $this->getNote(),
            'settings' => [
                'round' => get_option('wc_billingo_invoice_round'),
                'without_financial_fulfillment' => wcFlexibleIsTrue(get_option('mark_paid_without_financial_fulfillment')) && $paidType,
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
        // Get VAT number that was already retrieved in collectDocumentData
        $vatNumberFormCustom = get_option('wc_billingo_vat_number_form_custom');
        Billingo_Logger::info('VAT number form custom: ' . $vatNumberFormCustom);
        $vatNumber = null;
        if(!empty($vatNumberFormCustom)){
            $vatNumber = $this->order->get_meta($vatNumberFormCustom, true);
            Billingo_Logger::info('VAT number: ' . $vatNumber);
        }

        $descriptions = [
            'name' => $name,
            'address' => [
                'country_code' => $this->order->get_billing_country() ?: 'HU',
                'post_code' => $this->order->get_billing_postcode(),
                'city' => $this->order->get_billing_city(),
                'address' => $this->order->get_billing_address_1(),
            ],
        ];
        // Add VAT number to partner data if available
        if (!empty($vatNumber)) {
            $descriptions['taxcode'] = $vatNumber;
            Billingo_Logger::info("VAT number found for partner: {$name}");
        }

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

        if (($this->getSalePriceFromItemMeta($item) > 0) && 
            $this->getRegularPriceFromItemMeta($item, $product) > $this->getSalePriceFromItemMeta($item)) {
            
            // Itt is az elmentett regular price-t használjuk, ha elérhető
            $regularPrice = $this->getRegularPriceFromItemMeta($item, $product);
            $salePrice = $this->getSalePriceFromItemMeta($item);
            
            if (wcFlexibleIsTrue(get_option('woocommerce_prices_include_tax')) && $item->get_total_tax() > 0) {
                $salePrice = $salePrice * (1 + ($this->getVatRateFromCode($this->getCalculatedDateForItem('vat', $itemData)->value ?? '0%') / 100));
                $regularPrice = $regularPrice * (1 + ($this->getVatRateFromCode($this->getCalculatedDateForItem('vat', $itemData)->value ?? '0%') / 100));
            }

            Billingo_Logger::info('Discount calculation - Using regular price: ' . $regularPrice . ', Sale price: ' . $salePrice);
            Billingo_Logger::info('Regular price: ' . $regularPrice . ', Sale price: ' . $salePrice);
            
            // Nullás termékek (pl. ajándékok) kezelése
            if ($item->get_total() == 0) {
                $meta = $item->get_meta_data();
                if (isset($meta['type']) && stripos($meta['type'], 'free')) {
                    $salePrice = 0;
                }
            }

            if ($regularPrice > $salePrice) {
                $vatObject = $this->getCalculatedDateForItem('vat', $itemData)->value ?? '0%';
                if ($vatObject == null) {
                    $vatObject = VatEnum::PERCENT_0->value;
                }
                $vatRate = $this->getVatRateFromCode($vatObject);
                $netDiscount = ($regularPrice - $salePrice) * ($itemData['quantity'] ?? 1);
                $grossDiscount = $netDiscount * (1 + ($vatRate / 100));
    
                // WooCommerce árazási beállítás újra lekérése a kedvezmény számításhoz
                $priceIncludesTaxForDiscount = wcFlexibleIsTrue(get_option('woocommerce_prices_include_tax'));
                $itemIsTaxableForDiscount = $item->get_total_tax() > 0;
                Billingo_Logger::info('net discount: ' . $netDiscount);
                
                if (!$priceIncludesTaxForDiscount && $itemIsTaxableForDiscount) {
                    $discountAmount = $grossDiscount;
                } else {
                    $discountAmount = $netDiscount;
                }

                Billingo_Logger::info('Kedvezmény tétel hozzáadása bruttó értékkel: ' . $discountAmount);

                // Kedvezmény tétel hozzáadása bruttó értékkel
                $discountItem = new DocumentProductData([
                    'name' => __('Kedvezmény - ', 'billingo') . ($itemData['name'] ?? 'Termék'),
                    'quantity' => $itemData['quantity'] ?? 1,
                    'unit_price' => -$discountAmount / ($itemData['quantity'] ?? 1),
                    'unit_price_type' => UnitPriceTypeEnum::GROSS->value,
                    'unit' => $this->getCalculatedDateForItem('unit'),
                    'vat' => $this->getCalculatedDateForItem('vat', $itemData)->value ?? '0%',
                ]);

                // áfa felülírás ha kell
                if (wcFlexibleIsTrue(get_option('wc_billingo_tax_override'))) {
                    $discountItem = $this->overrideTax($discountItem);
                }
                
                $productItems[] = $discountItem;
            }
        }
        
        return $productItems;
    }

    /**
     * Alkalmazza a bundle kedvezményeket, ha engedélyezve van.
     * 
     * @param DocumentProductData[] $productItems Termék tételek tömbje
     * @param array $bundleData Bundle adatok tömbje
     * @param array $items Rendelési tételek tömbje
     * @return DocumentProductData[] A frissített termék tételek tömbje
     */
    private function applyBundleDiscounts(array $productItems, array $bundleData, array $items): array
    {
        // Ha a tételenkénti kedvezmény beállítás nincs bekapcsolva, csak visszaadjuk az eredeti tömböt
        if (!$this->isItemDiscountEnabled()) {
            Billingo_Logger::info('Bundle kedvezmény kihagyva - tételenkénti kedvezmények ki vannak kapcsolva');
            return $productItems;
        }
        
        Billingo_Logger::info('Bundle kedvezmény számítás kezdése - bundleData count: ' . count($bundleData));

        foreach ($bundleData as $cartKey => $bundle) {
            // Bundle kedvezmény számítás: Bundle részek összege - Bundle akciós ár
            $mainItem = $bundle['main_item'];
            $mainItemData = $mainItem->get_data();

            // Bundle részek összegzése (teljes áron)
            $bundleItemsPriceSum = 0;
            foreach ($bundle['bundled_items'] as $bundledItem) {
                $bundledItemData = $bundledItem->get_data();
                $bundledProduct = $bundledItem->get_product();
                $bundledItemPrice = $this->getRegularPriceFromItemMeta($bundledItem, $bundledProduct);

                // ÁFA számítás bundle részeknél is
                $price_includes_tax = wcFlexibleIsTrue(get_option('woocommerce_prices_include_tax'));
                $item_is_taxable = $bundledItem->get_total_tax() > 0;
                
                Billingo_Logger::info('bundledItemData total tax: ' . $bundledItem->get_total_tax());
                Billingo_Logger::info('bundledItemData tax class: ' . $bundledItemData['tax_class']);
                Billingo_Logger::info('bundledItemData item_is_taxable: ' . wcFlexibleIsTrue($item_is_taxable));
                Billingo_Logger::info('bundledItemData price_includes_tax: ' . wcFlexibleIsTrue($price_includes_tax));

                if(!$price_includes_tax && $item_is_taxable){
                    $vatObject = $this->getCalculatedDateForItem('vat', $bundledItemData);
                    $vatRate = 0;
                    if ($vatObject && $vatObject->value) {
                        $vatRate = $this->getVatRateFromCode($vatObject->value);
                    }
                    //$bundledItemData['tax_class'] != 'zero-rate'
                    Billingo_Logger::info('bundledItemData vatRate: ' . $vatRate);
                    $bundledItemPrice = $bundledItemPrice + ($bundledItemPrice * ($vatRate / 100));
                }
                Billingo_Logger::info('bundledItemPrice: ' . $bundledItemPrice);
                $bundleItemsPriceSum += $bundledItemPrice * ($bundledItemData['quantity'] ?? 1);
            }

            // Lekérjük a letárolt eredeti és akciós árakat (1 darab árát)
            $originalPricePerUnit = $this->getRegularPriceFromItemMeta($mainItem, $mainItem->get_product());
            $salePricePerUnit = $this->getSalePriceFromItemMeta($mainItem);
            $bundleQuantity = $mainItemData['quantity'] ?? 1;

            // Szorozzuk meg a mennyiséggel
            $originalPrice = $originalPricePerUnit * $bundleQuantity;
            $salePrice = $salePricePerUnit * $bundleQuantity;

            Billingo_Logger::info('Bundle kedvezmény számítás - ' . $bundle['bundle_name']);
            Billingo_Logger::info('Bundle részek összege: ' . $bundleItemsPriceSum);
            Billingo_Logger::info('Bundle eredeti ár per unit (meta): ' . $originalPricePerUnit);
            Billingo_Logger::info('Bundle akciós ár per unit (meta): ' . $salePricePerUnit);
            Billingo_Logger::info('Bundle mennyiség: ' . $bundleQuantity);
            Billingo_Logger::info('Bundle eredeti ár ÖSSZESEN: ' . $originalPrice);
            Billingo_Logger::info('Bundle akciós ár ÖSSZESEN: ' . $salePrice);

            // TELJES bundle kedvezmény = Bundle részek összege - Bundle akciós ár (összes mennyiségre) ITT KELL
            $bundleOwnDiscount = $originalPrice - $salePrice;
            //$bundleDiscount = $bundleItemsPriceSum - ($salePrice - $bundleOwnDiscount);
            $bundleDiscount = $bundleItemsPriceSum - $salePrice;

            // Debug: külön megmutatjuk a kedvezmény összetevőit
            // Bundle saját kedvezménye (ha akciós)
            $bundlePackageDiscount = $bundleItemsPriceSum - ($originalPrice - $bundleOwnDiscount); // Bundle csomag kedvezmény
            Billingo_Logger::info('belépünk');

            Billingo_Logger::info('Bundle saját kedvezmény (akció): ' . $bundleOwnDiscount . ' (' . $originalPrice . ' - ' . $salePrice . ')');
            Billingo_Logger::info('Bundle csomag kedvezmény: ' . $bundlePackageDiscount . ' (' . $bundleItemsPriceSum . ' - ' . $originalPrice . ')');
            Billingo_Logger::info('TELJES bundle kedvezmény: ' . $bundleDiscount . ' (' . $bundleItemsPriceSum . ' - ' . $salePrice . ')');

            if ($bundleDiscount > 0) {
                // $bundleQuantity már definiálva van feljebb

                // ÁFA számítás a bundle kedvezményhez
                $price_includes_tax = wcFlexibleIsTrue(get_option('woocommerce_prices_include_tax'));

                if(!$price_includes_tax){
                    // Nettó árazás esetén a kedvezmény is nettó, hozzá kell adni az ÁFA-t
                    $vatObject = $this->getCalculatedDateForItem('vat', $mainItemData);
                    $vatRate = 0;
                    if ($vatObject && $vatObject->value) {
                        $vatRate = $this->getVatRateFromCode($vatObject->value);
                    }

                    $grossDiscount = $bundleDiscount * (1 + ($vatRate / 100));
                    $unitDiscount = $grossDiscount / $bundleQuantity;

                    Billingo_Logger::info('Bundle kedvezmény ÁFA számítás: nettó=' . $bundleDiscount . ', ÁFA=' . $vatRate . '%, bruttó=' . $grossDiscount);
                } else {
                    // Bruttó árazás esetén a kedvezmény is bruttó
                    $unitDiscount = $bundleDiscount / $bundleQuantity;

                    Billingo_Logger::info('Bundle kedvezmény bruttó árazás: ' . $bundleDiscount);
                }
                $bundleDiscountItem = new DocumentProductData([
                    'name' => __('Csomag kedvezmény - ', 'billingo') . $bundle['bundle_name'],
                    'quantity' => $bundleQuantity,
                    'unit_price' => -$unitDiscount,
                    'unit_price_type' => UnitPriceTypeEnum::GROSS->value,
                    'unit' => $this->getCalculatedDateForItem('unit'),
                    'vat' => $this->getCalculatedDateForItem('vat', $mainItemData)->value ?? '0%',
                ]);

                // áfa felülírás ha kell
                if(wcFlexibleIsTrue(get_option('wc_billingo_tax_override'))){
                    $bundleDiscountItem = $this->overrideTax($bundleDiscountItem);
                }

                $productItems[] = $bundleDiscountItem;
                Billingo_Logger::info('Bundle kedvezmény tétel hozzáadva: ' . $bundleQuantity . ' × (-' . $unitDiscount . ') = -' . ($unitDiscount * $bundleQuantity) . ' (' . $bundle['bundle_name'] . ')');
            } else {
                Billingo_Logger::info('Nincs bundle kedvezmény (eredeti és akciós ár azonos)');
            }
        }
        
        return $productItems;
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
        // Ha a tételenkénti kedvezmény beállítás nincs bekapcsolva, csak visszaadjuk az eredeti tömböt
        /*if (!$this->isItemDiscountEnabled()) {
            Billingo_Logger::info('Kupon kedvezmény kihagyva - tételenkénti kedvezmények ki vannak kapcsolva');
            return $productItems;
        }*/
        
        // Kupon kedvezmény hozzáadása - minden kuponhoz külön tétel
        $usedCoupons = $this->order->get_coupon_codes();
        Billingo_Logger::info('Used coupons: ' . json_encode($usedCoupons));

        if (!empty($usedCoupons)) {
            // Minden egyes kuponhoz külön tételt hozunk létre
            foreach ($usedCoupons as $couponCode) {
                // Lekérjük a kupon kedvezmény összegét
                $couponDiscount = 0;

                // Megkeressük a kupon adatait a rendelés tételei között
                foreach ($this->order->get_items('coupon') as $couponItem) {
                    if ($couponItem->get_code() === $couponCode) {
                        $couponDiscount = abs(floatval($couponItem->get_discount()));
                        Billingo_Logger::info("Kupon '{$couponCode}' nyers kedvezmény: {$couponDiscount}");
                        break;
                    }
                }

                if ($couponDiscount > 0) {
                    // Ellenőrizzük, hogy szállítási kuponról van-e szó
                    $isShippingCoupon = $this->isCouponForShipping($couponCode);
                    
                    if ($isShippingCoupon) {
                        // Szállítási kupon esetén a szállítási ÁFA kulcsot használjuk
                        $vatCode = $this->getShippingCouponVatCode()->value ?? VatEnum::PERCENT_27->value;
                        //viszont ha a webáruházban a szállítási költség nettóban van megadva beállítás aktív, akkor a kuponra is rá kell számolni az áfát
                        if(wcFlexibleIsTrue(get_option('wc_billingo_tax_shipping_pirce_type_is_net'))){
                            $couponDiscount = $couponDiscount * (1 + ($this->getVatRateFromCode($vatCode) / 100));
                        }

                        Billingo_Logger::info("Szállítási kupon ÁFA kulcsa: {$vatCode}");
                    } else {
                        // Normál kupon esetén az első termék ÁFA kulcsát használjuk
                        $firstItem = reset($items);
                        $firstItemData = $firstItem ? $firstItem->get_data() : null;
                        $vatCode = $firstItemData ? $this->getCalculatedDateForItem('vat', $firstItemData)->value ?? VatEnum::PERCENT_27->value : VatEnum::PERCENT_27->value;
                        
                        $originalCouponDiscount = $couponDiscount;
                        
                        //ha a woocommerce beállítása szerint nettó árazás van, akkor a kuponra is rá kell számolni az áfát
                        //if(!wcFlexibleIsTrue(get_option('woocommerce_prices_include_tax'))){
                            $couponDiscount = $couponDiscount * (1 + ($this->getVatRateFromCode($vatCode) / 100));
                            Billingo_Logger::info("Kupon '{$couponCode}' ÁFA számítás: {$originalCouponDiscount} * (1 + {$this->getVatRateFromCode($vatCode)}%) = {$couponDiscount}");
                        /*} else {
                            Billingo_Logger::info("Kupon '{$couponCode}' bruttó árazás, ÁFA nem kerül hozzáadásra: {$couponDiscount}");
                        }*/
                    }
                    
                    if($vatCode == null){
                        $vatCode = VatEnum::PERCENT_0->value;
                    }

                    $discountItem = new DocumentProductData([
                        'name' => __('Kupon kedvezmény', 'billingo'),
                        'quantity' => 1,
                        'unit_price' => -$couponDiscount,
                        'unit_price_type' => UnitPriceTypeEnum::GROSS->value,
                        'unit' => $this->getCalculatedDateForItem('unit'),
                        'vat' => $vatCode,
                    ]);

                    // Áfa felülírás szállítási kupon esetén
                    if ($isShippingCoupon && wcFlexibleIsTrue(get_option('wc_billingo_tax_override_include_carrier'))) {
                        $discountItem = $this->overrideTax($discountItem);
                    } elseif (!$isShippingCoupon && wcFlexibleIsTrue(get_option('wc_billingo_tax_override'))) {
                        // Normál kupon esetén a standard ÁFA felülírás
                        $discountItem = $this->overrideTax($discountItem);
                    }
                    
                    $productItems[] = $discountItem;
                    Billingo_Logger::info("Kupon kedvezmény tétel hozzáadva: {$couponCode} (-{$couponDiscount} {$this->order->get_currency()}) " . ($isShippingCoupon ? "[SZÁLLÍTÁSI KUPON]" : "[TERMÉK KUPON]"));
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
            
            // Ellenőrizzük, hogy bundle részterméke-e vagy bundle főtermék-e
            $bundledBy = wc_get_order_item_meta($item->get_id(), '_bundled_by', true);
            $isBundleItem = !empty($bundledBy);
            $isBundleMainProduct = in_array($item, $bundleMainProducts);
            
            // Bundle főterméket kihagyjuk (később kedvezmény tételként kezeljük)
            /*if ($isBundleMainProduct) {
                Billingo_Logger::info('Bundle főtermék kihagyva a tételek közül: ' . $itemData['name']);
                continue;
            }*/
            
            if ($shouldSyncProducts && isset($productSync) && $product) {
                try {
                    $productSync->syncProduct($product, $itemData);
                } catch (\Exception $e) {
                    Billingo_Logger::error('Hiba a termék szinkronizálásakor: ' . $e->getMessage());
                }
            }
            Billingo_Logger::info('item tax class: ' . $itemData['tax_class']);
            // Bundle résztermékeknél a teljes árat használjuk, egyéb termékeknél az eredeti logikát
            if ($isBundleItem) {
                //$unitPrice = $this->getRegularPriceFromItemMeta($item, $product);
                $unitPrice = $item->get_subtotal()/$item->get_quantity();

                Billingo_Logger::info('Bundle részterméke - teljes ár használata: ' . $unitPrice . ' (' . $itemData['name'] . ')');
                
                // Bundle részek esetén kényszerítjük az ÁFA számítást, mert a WooCommerce 0-ra állítja
                $price_includes_tax = wcFlexibleIsTrue(get_option('woocommerce_prices_include_tax'));

                // Bundle részek esetén mindig adóköteles tételként kezeljük kivéve ha a tax_class zero-rate
                $item_is_taxable = $itemData['tax_class'] != 'zero-rate';

                Billingo_Logger::info('Bundle részterméke - kényszerített ÁFA számítás: price_includes_tax=' . ($price_includes_tax ? 'yes' : 'no') . ', forced_taxable=yes');
                
                if(!$price_includes_tax && $item_is_taxable){
                    // WooCommerce nettó árazás: Regular price-hoz tartozó ÁFA kiszámítása
                    $vatObject = $this->getCalculatedDateForItem('vat', $itemData);
                    $vatRate = 0;
                    if ($vatObject && $vatObject->value) {
                        $vatRate = $this->getVatRateFromCode($vatObject->value);
                    }
                    
                    $regularPrice = $unitPrice; // Már kiszámítottuk a helper metódussal
                    $regularPriceTax = $regularPrice * ($vatRate / 100);
                    
                    $unitPrice = $item->get_subtotal() + $item->get_subtotal_tax();
                    
                    Billingo_Logger::info('Bundle részterméke - ÁFA számítás: nettó=' . $regularPrice . ', ÁFA kulcs=' . $vatRate . '%, ÁFA összeg=' . $regularPriceTax . ', bruttó=' . $unitPrice);
                } else {
                    // WooCommerce bruttó árazás VAGY nem adóköteles tétel: a regular price már bruttó
                    
                    Billingo_Logger::info('Bundle, áfa hozzáadás nem szükséges, áfát tartalmaz, vagy zero-rate tax class');
                    Billingo_Logger::info('Bundle részterméke - bruttó árazás, de nettóról számolva: nettó=' . $regularPrice . ', ÁFA kulcs=' . $vatRate . '%, ÁFA összeg=' . $regularPriceTax . ', bruttó=' . $unitPrice);
                }
            } else {
                //$unitPrice = $this->getRegularPriceFromItemMeta($item, $product);
                $unitPrice = $item->get_subtotal()/$item->get_quantity();

                // Vizsgáljuk meg a WooCommerce árazási beállításokat
                $price_includes_tax = wcFlexibleIsTrue(get_option('woocommerce_prices_include_tax'));
                $item_is_taxable = (($item->get_total_tax() > 0) && $itemData['tax_class'] != 'zero-rate');



                Billingo_Logger::info('Price calculation - Original unit price: ' . $unitPrice);
                Billingo_Logger::info('Price includes tax setting: ' . ($price_includes_tax ? 'yes' : 'no'));
                Billingo_Logger::info('Item is taxable: ' . ($item_is_taxable ? 'yes' : 'no'));
                Billingo_Logger::info('Item total: ' . $item->get_total() . ', Item tax: ' . $item->get_total_tax() . ', Quantity: ' . $item->get_quantity());

                // Mindig bruttó árat küldünk a Billingo-nak
                // A bruttó ár = nettó ár + ÁFA (item total + item tax)
                if(!$price_includes_tax && $item_is_taxable){

                    // WooCommerce nettó árazás: Regular price-hoz tartozó ÁFA kiszámítása
                    $vatObject = $this->getCalculatedDateForItem('vat', $itemData) ?? '0%';
                    $vatRate = 0;
                    if ($vatObject && $vatObject->value) {
                        $vatRate = $this->getVatRateFromCode($vatObject->value);
                    }

                    $regularPrice = $unitPrice; // Már kiszámítottuk a helper metódussal
                    $regularPriceTax = $regularPrice * ($vatRate / 100);

                    $unitPrice = $item->get_subtotal()/$item->get_quantity() * (1 + $vatRate / 100);

                    Billingo_Logger::info('WC nettó árazás - ÁFA számítás: nettó=' . $regularPrice . ', ÁFA kulcs=' . $vatRate . '%, ÁFA összeg=' . $regularPriceTax . ', bruttó=' . $unitPrice);
                    Billingo_Logger::info('Regular price: ' . $regularPrice . ', VAT rate: ' . $vatRate . '%, Tax amount: ' . $regularPriceTax . ', Final gross price: ' . $unitPrice);
                } else {

                    // WooCommerce bruttó árazás VAGY nem adóköteles tétel: a regular price már bruttó
                    // Az unitPrice már kiszámítottuk a helper metódussal, nincs mit tenni

                    //ha a termék brutto akkor bruttoval hozzuk létre az árat

                    //de ha mégis ide fut be a woocom beállítások miatt és láthatóan tartalmaz áfát akkor adjuk hozzá az áfát is.
                    if($item->get_total_tax() > 0){
                        Billingo_Logger::info('áfát tartalmazott a termék, adjuk hozzá az áfát is');
                        $regular_price = $this->getRegularPriceFromItemMeta($item, $product);

                        $vatObject = $this->getCalculatedDateForItem('vat', $itemData) ?? '0%';
                        $vatRate = 0;
                        if ($vatObject && $vatObject->value) {
                            $vatRate = $this->getVatRateFromCode($vatObject->value);
                        }
                        $regular_price_tax = $regular_price * ($vatRate / 100);
                        $unitPrice = ($item->get_subtotal()+$item->get_subtotal_tax())/$item->get_quantity();
                    }

                    Billingo_Logger::info('WC bruttó árazás vagy nem adóköteles - Regular price as gross: ' . $unitPrice);
                }
            }
            
            Billingo_Logger::info('Final gross unit price sent to Billingo: ' . $unitPrice);

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

                // áfa felülírás ha kell
                if(wcFlexibleIsTrue(get_option('wc_billingo_tax_override'))){
                    $originalItem = $this->overrideTax($originalItem);
                }

                $productItems[] = $originalItem;
            } catch (\Exception $e) {
                Billingo_Logger::error('Hiba a dokumentum elem létrehozásakor: ' . $e->getMessage());
                // Folytatjuk a következő elemmel
                continue;
            }
            
            // Alkalmazzuk a tételenkénti kedvezményeket, ha engedélyezve van
            $productItems = $this->applyItemDiscounts($productItems, $item, $itemData, $product, $isBundleItem);
        }
        
        // Bundle kedvezmények hozzáadása (ha engedélyezve van)
        $productItems = $this->applyBundleDiscounts($productItems, $bundleData, $items);
        
        // Kupon kedvezmények hozzáadása (ha engedélyezve van)
        $productItems = $this->applyCouponDiscounts($productItems, $items);
        
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

                $shippingVatCalculated = $shippingTotal + $shippingMethod->get_total_tax();
                if (!wcFlexibleIsTrue(get_option('wc_billingo_tax_shipping_pirce_type_is_net'))) {
                    $shippingVatCalculated = $shippingTotal;
                }else{
                    $shippingVatCalculated = $shippingTotal + $shippingMethod->get_total_tax();
                }
                if ($shippingTotal > 0) {
                    // Szállítási tétel létrehozása a tényleges összeggel
                    $shippingItem = new DocumentProductData([
                        'name' => !empty($shippingMethodTitle) 
                            ? $shippingMethodTitle 
                            : __('Szállítás', 'billingo'),
                        'quantity' => 1,
                        'unit_price' => $shippingVatCalculated,
                        'unit_price_type' => UnitPriceTypeEnum::GROSS->value,
                        'unit' => $this->getCalculatedDateForItem('unit'),
                        'vat' => $this->getShippingVatCode($shippingMethod)->value,
                        'comment' => ''
                    ]);
                    
                    //áfa felülírás szállítási költség esetén is
                    if(wcFlexibleIsTrue(get_option('wc_billingo_tax_override_include_carrier'))){
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
                    if(wcFlexibleIsTrue(get_option('wc_billingo_tax_override_include_carrier'))){
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
                    // Tranzakciós díj ÁFA kulcsának meghatározása
                    $feeVatCode = $this->getFeeVatCode($fee);
                    Billingo_Logger::info('Fee ÁFA kulcs: ' . $feeVatCode->value);
                    
                    // A bruttó összeg kiszámítása
                    $grossAmount = $feeTotal;


                    // Ha WooCommerce nettó árazást használ és van ÁFA, akkor hozzáadjuk az ÁFA-t
                    if (!wcFlexibleIsTrue(get_option('woocommerce_prices_include_tax')) && $feeTax > 0) {
                        $grossAmount = $feeTotal + $feeTax;
                        Billingo_Logger::info('Nettó ár + ÁFA: ' . $feeTotal . ' + ' . $feeTax . ' = ' . $grossAmount);
                    } else {
                        Billingo_Logger::info('Bruttó ár használata: ' . $grossAmount);
                    }
                    
                    $feeItem = new DocumentProductData([
                        'name' => !empty($feeName)
                            ? $feeName
                            : __('Tranzakciós költség', 'billingo'),
                        'quantity' => 1,
                        'unit_price' => $grossAmount,
                        'unit_price_type' => UnitPriceTypeEnum::GROSS->value,
                        'unit' => $this->getCalculatedDateForItem('unit'),
                        'vat' => $feeVatCode->value,
                        'comment' => ''
                    ]);

                    // áfa felülírás ha kell
                    if(wcFlexibleIsTrue(get_option('wc_billingo_tax_override'))){
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
     * Tranzakciós díj ÁFA kulcs meghatározása
     * @param mixed $fee WooCommerce fee objektum
     * @return VatEnum
     */
    private function getFeeVatCode($fee): VatEnum
    {
        Billingo_Logger::info('Fee ÁFA kulcs meghatározása - Fee objektum típusa: ' . get_class($fee));
        
        // Ha van fee objektum, próbáljuk meg lekérni az ÁFA kulcsot
        if ($fee && method_exists($fee, 'get_taxes')) {
            $taxes = $fee->get_taxes();
            Billingo_Logger::info('Fee taxes: ' . json_encode($taxes));
            
            if (!empty($taxes)) {
                // Az első ÁFA kulcsot használjuk
                $taxRateId = array_key_first($taxes);
                if ($taxRateId) {
                    $taxRate = WC_Tax::_get_tax_rate($taxRateId);
                    Billingo_Logger::info('Tax rate data: ' . json_encode($taxRate));
                    
                    if ($taxRate && isset($taxRate['tax_rate'])) {
                        $vatEnum = VatEnum::fromNumber($taxRate['tax_rate']);
                        if ($vatEnum) {
                            Billingo_Logger::info('Fee ÁFA kulcs megtalálva: ' . $vatEnum->value . ' (' . $taxRate['tax_rate'] . '%)');
                            return $vatEnum;
                        }
                    }
                }
            }
        }
        
        // Próbáljunk meg az ÁFA osztályt lekérni a fee-ből ha van tax_class property
        if ($fee && method_exists($fee, 'get_tax_class')) {
            $taxClass = $fee->get_tax_class();
            Billingo_Logger::info('Fee tax class: ' . $taxClass);
            
            if (!empty($taxClass)) {
                $taxRates = WC_Tax::get_rates_for_tax_class($taxClass);
                if (!empty($taxRates)) {
                    $firstRate = reset($taxRates);
                    if (isset($firstRate->tax_rate)) {
                        $vatEnum = VatEnum::fromNumber($firstRate->tax_rate);
                        if ($vatEnum) {
                            Billingo_Logger::info('Fee ÁFA kulcs tax class alapján: ' . $vatEnum->value . ' (' . $firstRate->tax_rate . '%)');
                            return $vatEnum;
                        }
                    }
                }
            }
        }
        
        // Ha nem sikerült meghatározni a tranzakciós díj ÁFA kulcsát, 
        // akkor az alapértelmezett 27%-ot használjuk (magyar standard)
        Billingo_Logger::info('Fee ÁFA kulcs nem található, alapértelmezett 27% használata');
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

            if($item['tax_class'] == 'zero-rate'){
                $vat = 0;
            }
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
        $defaultNoteOptionKey = get_option('wc_billingo_note');
        $defaultNote = '';
        
        if (!empty($defaultNoteOptionKey)) {
            $defaultNote = $defaultNoteOptionKey;
        }
        
        $note = empty($this->order->get_customer_note())
            ? $defaultNote
            : $this->order->get_customer_note() ?? '';

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
            : get_option('wc_billingo_auto');

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
        $typeisZero = get_option('wc_billingo_tax_override_choice') == 0;
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
        $value = $typeisZero
            ? $entitlemet
            : VatEnum::from(get_option('wc_billingo_tax_override_value'))->value;
        Billingo_Logger::info('Áfa felülírás ÁFA ÉRTÉKE: ' . $value);

        if (!$typeisZero) {
            $item->vat = $value;
            $item->entitlement = $entitlemet;
        } else {
            $item->vat = $value;
        }
        Billingo_Logger::info('=== ===');
        return $item;
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

    /**
     * Megállapítja, hogy egy kupon a szállításhoz tartozik-e
     * (WooCommerce Extended Coupon Features FREE plugin alapján)
     * 
     * @param string $couponCode A kupon kódja
     * @return bool True, ha szállítási kupon, false egyébként
     */
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
     * @return VatEnum
     */
    private function getShippingCouponVatCode(): VatEnum
    {
        $shippingMethods = $this->order->get_shipping_methods();
        
        if (!empty($shippingMethods)) {
            // Az első szállítási módszer ÁFA kulcsát használjuk
            $firstShippingMethod = reset($shippingMethods);
            return $this->getShippingVatCode($firstShippingMethod);
        }
        
        // Ha nincs szállítási módszer, akkor a standard 27%-ot használjuk
        return VatEnum::PERCENT_27;
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


        
        // Ha nincs elmentve, akkor a termékből lekérjük
        if ($product && method_exists($product, 'get_regular_price')) {
            $regularPrice = $product->get_regular_price();
            if (!empty($regularPrice)) {
                Billingo_Logger::info('Using current regular price from product: ' . $regularPrice);
                return floatval($regularPrice);
            }
        }
        
        // Fallback: ha semmi más nem működik, az item total/quantity arányából számoljuk
        $itemData = $item->get_data();
        if (isset($itemData['total']) && isset($itemData['quantity']) && $itemData['quantity'] > 0) {
            $calculatedPrice = $itemData['total'] / $itemData['quantity'];
            Billingo_Logger::info('Using calculated price from item total/quantity: ' . $calculatedPrice);
            return floatval($calculatedPrice);
        }
        
        Billingo_Logger::warning('Could not determine regular price, returning 0');
        return 0.0;
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
            return $item->get_subtotal() / ($item->get_quantity() ?? 1);
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

        Billingo_Logger::warning('getCurrencyRate FAILED - no API rate available and no manual rate set, falling back to 1.0');
        return 1.0;
    }

}

