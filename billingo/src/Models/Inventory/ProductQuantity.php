<?php

namespace App\Billingo\Models\Inventory;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Validation\Inventory\ProductQuantityValidator;

/**
 * @property float $available_quantity
 */
class ProductQuantity extends BillingoModel
{

    protected function getValidator(): ValidableInterface
    {
        return new ProductQuantityValidator();
    }
}
