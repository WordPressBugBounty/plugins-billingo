<?php

namespace App\Billingo\Models;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Validation\AddressValidator;
use App\Billingo\Traits\WithFactory;

/**
 * @property string $country_code
 * @property string $post_code
 * @property string $city
 * @property string $address
 */
class Address extends BillingoModel
{

    use WithFactory;
    protected function getValidator(): ValidableInterface
    {
        return new AddressValidator();
    }
}
