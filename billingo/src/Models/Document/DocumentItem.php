<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\Document\DocumentItemValidator;

/**
 * @property integer $product_id
 * @property string $name
 * @property float $net_unit_amount
 * @property float $quantity
 * @property string $unit
 * @property float $net_amount
 * @property float $gross_amount
 * @property string $vat
 * @property float $vat_amount
 * @property string $entitlement
 * @property string $comment
 * @property string $sku
 * @property string $is_generate_erase_code
 */
class DocumentItem extends BillingoModel
{

    use WithFactory;

    protected function getValidator(): ValidableInterface
    {
        return new DocumentItemValidator();
    }
}
