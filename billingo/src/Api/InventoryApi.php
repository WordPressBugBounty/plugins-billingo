<?php

namespace App\Billingo\Api;

use App\Billingo\Enums\HttpMethodEnum;
use App\Billingo\Models\Inventory\ProductQuantity;

class InventoryApi extends BillingoApi
{
    const PREFIX = 'inventory';
    const DEFAULT_CONVERT_CLASS = ProductQuantity::class;

    protected static string $convertTo = self::DEFAULT_CONVERT_CLASS;

    /**
     * Retrieves product quantity from the default warehouse.
     * @param  int  $id
     * @return self
     */
    public function getQuantity(int $id): self
    {
        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX."/product/{$id}/quantity");

        return $this->send();
    }
}
