<?php

namespace App\Billingo\Models;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\DiscountValidator;

/**
 * @property string $type
 * @property float $value
 */
class Discount extends BillingoModel
{

    use WithFactory;

    protected function getValidator(): ValidableInterface
    {
        return new DiscountValidator();
    }
}
