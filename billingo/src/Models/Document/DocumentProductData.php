<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\Document\DocumentProductDataValidation;

/**
 * @property string $name
 * @property float $unit_price
 * @property string $unit_price_type
 * @property float $quantity
 * @property string $unit
 * @property string $vat
 * @property string $comment
 * @property string $entitlement
 * @property string $sku
 * @property string $is_generate_erase_code
 */
class DocumentProductData extends BillingoModel
{

    use WithFactory;

    protected function getValidator(): ValidableInterface
    {
        return new DocumentProductDataValidation();
    }
}
