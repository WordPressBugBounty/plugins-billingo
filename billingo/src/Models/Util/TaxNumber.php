<?php

namespace App\Billingo\Models\Util;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Validation\Util\TaxNumberValidator;

/**
 * @property string $tax_number
 * @property string $result
 */
class TaxNumber extends BillingoModel
{

    protected function getValidator(): ValidableInterface
    {
        return new TaxNumberValidator();
    }
}
