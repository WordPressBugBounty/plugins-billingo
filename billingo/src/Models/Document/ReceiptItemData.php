<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Validation\Document\ReceiptItemValidator;

/**
 * @property int $product_id
 */
class ReceiptItemData extends BillingoModel
{

    protected function getValidator(): ValidableInterface
    {
        return new ReceiptItemValidator();
    }
}
