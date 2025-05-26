<?php

namespace App\Billingo\Models\Product;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\Product\ProductValidator;

/**
 * @property int $id
 * @property string $name
 * @property string $comment
 * @property string $currency
 * @property string $vat
 * @property float $net_unit_price
 * @property float $gross_unit_price
 * @property string $unit
 * @property string $general_ledger_number
 * @property string $general_ledger_taxcode
 * @property string $entitlement
 * @property string $ean
 * @property string $sku
 * @property bool $is_manage
 * @property float $purchase_price
 * @property bool $is_generate_erase_code
 */

class Product extends BillingoModel
{
    use WithFactory;

    protected function getValidator(): ValidableInterface
    {
        return new ProductValidator();
    }
}
