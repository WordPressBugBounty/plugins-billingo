<?php

namespace App\Billingo\Service;

use App\Billingo\Api\BankAccountApi;
use App\Billingo\Api\BuilderApi;
use App\Billingo\Api\CurrencyApi;
use App\Billingo\Api\DocumentApi;
use App\Billingo\Api\DocumentBlockApi;
use App\Billingo\Api\DocumentExportApi;
use App\Billingo\Api\InventoryApi;
use App\Billingo\Api\OrganizationApi;
use App\Billingo\Api\PartnerApi;
use App\Billingo\Api\ProductApi;
use App\Billingo\Api\SpendingApi;
use App\Billingo\Api\UtilApi;

class BillingoClient
{
    public function __construct(string $apiKey = null)
    {
        if (is_null($apiKey)) {
            $config = require __DIR__ . '/../config.php';
            $apiKey = $config['api_key'];
        }

        BillingoConnector::getInstance($apiKey);
    }

    public function builder(): BuilderApi
    {
        return new BuilderApi();
    }

    public function document(): DocumentApi
    {
        return new DocumentApi();
    }

    public function documentBlock(): DocumentBlockApi
    {
        return new DocumentBlockApi();
    }

    public function partner(): PartnerApi
    {
        return new PartnerApi();
    }

    public function product(): ProductApi
    {
        return new ProductApi();
    }

    public function bankAccount(): BankAccountApi
    {
        return new BankAccountApi();
    }

    public function currency(): CurrencyApi
    {
        return new CurrencyApi();
    }

    public function documentExport(): DocumentExportApi
    {
        return new DocumentExportApi();
    }

    public function inventory(): InventoryApi
    {
        return new InventoryApi();
    }

    public function organization(): OrganizationApi
    {
        return new OrganizationApi();
    }

    public function spending(): SpendingApi
    {
        return new SpendingApi();
    }

    public function util(): UtilApi
    {
        return new UtilApi();
    }
}
