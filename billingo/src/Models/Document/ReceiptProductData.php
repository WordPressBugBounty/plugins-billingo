<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Validation\Document\ReceiptProductValidator;

/**
 * @property string $name
 * @property float $unit_price
 * @property string $vat
 */
class ReceiptProductData extends BillingoModel
{

    protected function getValidator(): ValidableInterface
    {
        return new ReceiptProductValidator();
    }
}
