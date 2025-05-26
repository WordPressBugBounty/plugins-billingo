<?php

namespace App\Billingo\Models\Partner;

use App\Billingo\Models\Address;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\Partner\PartnerShippingValidator;

/**
 * @property boolean $match
 * @property string $name
 * @property string $mode
 * @property Address $address
 */
class PartnerShipping extends BillingoModel
{
    use WithFactory;

    protected array $cast = [
        'address' => Address::class,
    ];

    protected function getValidator(): ValidableInterface
    {
        return new PartnerShippingValidator();
    }
}
