<?php

namespace App\Billingo\WooCommerce\Service;

use App\Billingo\Enums\VatEnum;
use App\Billingo\Models\Product\Product;
use App\Billingo\Service\BillingoClient;

/**
 * Service to handle synchronization of products between WooCommerce and Billingo, if a new invoice is created
 */
class Billingo_Product_Sync
{
    private BillingoClient $client;
    private array $productCache = [];
    
    /**
     * Initialize with API client
     */
    public function __construct()
    {
        $this->client = new BillingoClient(get_option('wc_billingo_api_key', ''));
    }
    
    /**
     * Sync a WooCommerce product with Billingo
     * 
     * @param \WC_Product $product WooCommerce product
     * @param array $itemData Order item data for pricing and tax info
     * @return bool Success of synchronization
     */
    public function syncProduct(\WC_Product $product, array $itemData): bool
    {
        try {
            // Ellenőrizzük, hogy a client létezik-e
            if (!$this->client) {
                Billingo_Logger::error("Billingo client nincs inicializálva!");
                return false;
            }
            
            $sku = $product->get_sku();
            
            // Skip products without SKU
            if (empty($sku)) {
                Billingo_Logger::info("A terméknek nincs SKU-ja: " . $product->get_name());
                return false;
            }
            
            // Prepare product data
            $productData = $this->prepareProductData($product, $itemData);
            
            // Ellenőrizzük, hogy a szükséges mezők megvannak-e
            if (empty($productData['name']) || !isset($productData['vat']) || !isset($productData['net_unit_price']) || !isset($productData['sku']) ) {
                Billingo_Logger::error("Hiányzó termék adatok: " . json_encode($productData));
                return false;
            }
            
            // Find existing product in Billingo
            $existingProduct = $this->findProductBySku($sku);
            
            // Termék létrehozása vagy frissítése egy metódusban
            return $this->createOrUpdateProduct($existingProduct, $productData);
        } catch (\Exception $e) {
            Billingo_Logger::error('A termék szinkronizálási hiba: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Prepare product data from WooCommerce product
     * 
     * @param \WC_Product $product WooCommerce product
     * @param array $itemData Order item data
     * @return array Product data for Billingo API
     */
    private function prepareProductData(\WC_Product $product, array $itemData): array
    {
        $sku = $product->get_sku();
        $name = $product->get_name();

        // Get stock management status
        $is_manage = $product->get_manage_stock();

        $netPrice = isset($itemData['total']) && isset($itemData['quantity']) && $itemData['quantity'] > 0 
            ? $itemData['total'] / $itemData['quantity'] 
            : $product->get_price();
        
        //purchase price - beszerzési ár
        $purchase_price = $purchase_price = get_post_meta( $product->get_id(), '_purchase_price', true );
        // Get VAT code from tax class
        $vatCode = VatEnum::PERCENT_27->value; // Default
        if (isset($itemData['tax_class'])) {
            try {
                $taxRates = \WC_Tax::get_rates_for_tax_class($itemData['tax_class']);
                if (!empty($taxRates)) {
                    $taxRate = reset($taxRates);
                    $vatRate = $taxRate->tax_rate ?? 27;
                    $vatEnum = VatEnum::fromNumber($vatRate);
                    $vatCode = $vatEnum->value;
                }
            } catch (\Exception $e) {
                Billingo_Logger::warning('Hiba az ÁFA kulcs meghatározásakor: ' . $e->getMessage() . ' - Alapértelmezett (27%) használata');
                // Ha hiba van, maradunk az alapértelmezett értéknél
            }
        }
        
        // Valuta beállítása - biztosítsuk, hogy érvényes legyen
        $currency = strtoupper(get_option('woocommerce_currency', 'HUF'));
        
        // Ellenőrizzük, hogy a valuta szerepel-e a CurrencyEnum-ban
        $validCurrency = true;
        try {
            // CurrencyEnum példányt
            $currencyEnum = \App\Billingo\Enums\CurrencyEnum::from($currency);
        } catch (\ValueError $e) {
            Billingo_Logger::warning('Érvénytelen valuta kód: ' . $currency . ' - HUF használata helyette');
            $currency = 'HUF';
            $validCurrency = false;
        }
        
        // Egység beállítása - biztosítsuk, hogy ne legyen üres
        $unit = get_option('wc_billingo_unit', 'db');
        if (empty($unit)) {
            $unit = 'db';
        }
        
        $comment = $product->get_purchase_note();
        $product_id = $product->get_id();

        //vonalkód, isbn-kód, gtin-kód,
        $possible_keys = ['_ean', '_product_ean', '_wpm_gtin_code', '_wc_gtin_code', '_alg_ean',];

        foreach ( $possible_keys as $key ) {
            $ean = get_post_meta( $product_id, $key, true );
            if ( ! empty( $ean ) ) {
                Billingo_Logger::info("EAN megtalálva ($key): $ean");
                break;
            }
        }
        if (empty($ean)) {
            try {
                $ean = $product->get_global_unique_id();
            } catch (\Exception $e) {
                Billingo_Logger::error("EAN nem található: " . $e->getMessage());
            }
        }

        $erase_code = $this->isProductNeedsEraseCode($product_id);


        $productData = [
            'name' => $name,
            'comment' => $comment, // purchase note
            'currency' => $currency,
            'vat' => $vatCode,
            'net_unit_price' => $netPrice,
            'purchase_price' => $purchase_price,
            'unit' => $unit,
            'sku' => $sku,
            'is_manage' => $is_manage,
            'is_generate_erase_code' => $erase_code,
        ];

        if (!empty($ean)) {
            $productData['ean'] = $ean;
        }

        // Ellenőrizzük, hogy a termék adatok megfelelnek-e minden követelménynek
        if (empty($productData['name'])) {
            Billingo_Logger::error('Hiányzó termék név!');
        }
        
        if (empty($productData['vat'])) {
            Billingo_Logger::error('Hiányzó ÁFA kulcs!');
        }
        
        if (empty($productData['unit'])) {
            Billingo_Logger::error('Hiányzó egység!');
        }
        
        if (!$validCurrency) {
            Billingo_Logger::error('Érvénytelen valuta kód: ' . get_option('woocommerce_currency', 'HUF') . ' - HUF használata helyette');
        }
        
        Billingo_Logger::info('Elkészített termék adatok: ' . json_encode($productData));
        
        return $productData;
    }
    
    /**
     * Find a product in Billingo by SKU
     * 
     * @param string $sku Product SKU
     * @return object|null Product object or null
     */
    public function findProductBySku(string $sku): ?object
    {
        // Check cache first
        if (isset($this->productCache[$sku])) {
            //Billingo_Logger::info("Termék megtalálva a cache-ben: {$sku}");
            return $this->productCache[$sku];
        }
        
        //Billingo_Logger::info("Termék keresése a Billingo rendszerben: {$sku}");
        
        try {
            $productApi = $this->client->product();
            $foundProduct = null;
            $page = 1;
            $perPage = 100;

            // Lapozva végigmegyünk a teljes terméklistán, amíg meg nem találjuk az SKU-t,
            // vagy amíg el nem fogynak az oldalak (kevesebb elem jön vissza, mint amennyit kértünk).
            do {
                $response = $productApi->getAll(['page' => $page, 'per_page' => $perPage])->getResponse();

                if (!$response || $response->getStatusCode() !== 200) {
                    Billingo_Logger::error("Hiba a termékek lekérésekor ({$page}. oldal): " . ($response ? $response->getStatusCode() : 'Nincs válasz'));
                    return null;
                }

                $data = $response->getData();

                if (!($data instanceof \App\Billingo\Service\BillingoCollection)) {
                    Billingo_Logger::error("Nem támogatott válasz formátum: " . gettype($data));
                    return null;
                }

                $items = $data->get();

                foreach ($items as $product) {
                    if (!isset($product->sku)) {
                        continue;
                    }

                    // Minden bejárt terméket cache-elünk, hogy a következő SKU-keresés ne induljon újra elölről.
                    $this->productCache[$product->sku] = $product;

                    if ($foundProduct === null && trim(strtolower($product->sku)) === trim(strtolower($sku))) {
                        Billingo_Logger::info("Termék egyezés! SKU: {$sku}, ID: {$product->id}");
                        $foundProduct = $product;
                    }
                }

                $page++;
            } while ($foundProduct === null && count($items) === $perPage);

            return $foundProduct;
        } catch (\Exception $e) {
            Billingo_Logger::error("Kivétel a termék keresésekor: " . $e->getMessage());
            Billingo_Logger::error("Stack trace: " . $e->getTraceAsString());
            return null;
        }
    }
    
    /**
     * Create a new product or update existing one based on SKU
     * 
     * @param object|null $existingProduct Existing product if found
     * @param array $productData Product data to create/update
     * @return bool Success of operation
     */
    private function createOrUpdateProduct(?object $existingProduct, array $productData): bool
    {
        try {
            $sku = $productData['sku'] ?? 'ismeretlen';
            $productName = $productData['name'] ?? 'ismeretlen nevű';
            
            // Objektum létrehozása a Product modellel
            $productObj = new Product($productData);
            
            // Validáljuk a terméket
            if ($productObj->hasError()) {
                $errors = $productObj->getErrors();
                if ($errors) {
                    $errorDetails = is_object($errors) && method_exists($errors, 'getValues') ? $errors->getValues() : [];
                    Billingo_Logger::error("Termék validációs hiba: " . json_encode($errorDetails));
                    return false;
                }
            }
            
            // A BillingoCollection objektum
            //Billingo_Logger::info("sku értékek összehasonlítása if előtt: " . $existingProduct->sku . "==" . $sku);
            if ($existingProduct !== null && isset($existingProduct->sku) && trim(strtolower($existingProduct->sku)) === trim(strtolower($sku))) {
                // Meglévő termék frissítése
        
                $productId = $existingProduct->id;
                Billingo_Logger::info("Termék frissítése: '{$productName}' (SKU: {$sku}, ID: {$productId})");
                
                try {
                    $response = $this->client
                        ->product()
                        ->update($productId, $productObj)
                        ->getResponse();
                        
                    if (!$response) {
                        Billingo_Logger::error("Nincs válasz a termék frissítésekor");
                        return false;
                    }
                    
                    $statusCode = $response->getStatusCode();
                    //Billingo_Logger::info("Termék frissítés API válasz: HTTP {$statusCode}");
                    
                    if ($statusCode === 200) {
                        $updatedProduct = $response->getData();
                        $this->productCache[$sku] = $updatedProduct;
                        Billingo_Logger::info("Termék sikeresen frissítve: '{$productName}' (SKU: {$sku})");
                        return true;
                    } else {
                        $errors = $response->getErrors();
                        Billingo_Logger::error("Termék frissítési hiba: " . json_encode($errors));
                        return false;
                    }
                } catch (\App\Billingo\Exceptions\BadContentException $e) {
                    Billingo_Logger::error("BadContent kivétel a termék frissítésekor: " . $e->getMessage());
                    return false;
                } catch (\Exception $e) {
                    Billingo_Logger::error("Általános kivétel a termék frissítésekor: " . $e->getMessage());
                    return false;
                }
            } else {
                // Új termék létrehozása
                Billingo_Logger::info("Új termék létrehozása: '{$productName}' (SKU: {$sku})");
                
                try {
                    $response = $this->client
                        ->product()
                        ->create($productObj)
                        ->getResponse();
                        
                    if (!$response) {
                        Billingo_Logger::error("Nincs válasz a termék létrehozásakor");
                        return false;
                    }
                    
                    $statusCode = $response->getStatusCode();
                    Billingo_Logger::info("Termék létrehozás API válasz: HTTP {$statusCode}");
                    
                    if ($statusCode === 201) {
                        $createdProduct = $response->getData();
                        if ($createdProduct) {
                            $this->productCache[$sku] = $createdProduct;
                            Billingo_Logger::info("Termék sikeresen létrehozva: '{$productName}' (SKU: {$sku})");
                            return true;
                        }
                    } else {
                        $errors = $response->getErrors();
                        Billingo_Logger::error("Termék létrehozási hiba: " . json_encode($errors));
                        return false;
                    }
                } catch (\App\Billingo\Exceptions\BadContentException $e) {
                    Billingo_Logger::error("BadContent kivétel a termék létrehozásakor: " . $e->getMessage());
                    return false;
                } catch (\Exception $e) {
                    Billingo_Logger::error("Általános kivétel a termék létrehozásakor: " . $e->getMessage());
                    return false;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            Billingo_Logger::error("Nem várt hiba történt: " . $e->getMessage());
            return false;
        }
    }


    private function isProductNeedsEraseCode($productId): bool
    {
        $fieldName = get_option('wc_billingo_is_generate_erase_code', false);
        if (!$fieldName) {

            return false;
        }

        return get_post_meta($productId, '_product_attributes',true)[$fieldName]['value'] ?? false;
    }
    
}