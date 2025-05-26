<?php

namespace App\Billingo\Models;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Validation\SubscriptionValidator;

/**
 * @property string $expiration_date
 * @property string[] $features
 */
class Subscription extends BillingoModel
{

    protected function getValidator(): ValidableInterface
    {
        return new SubscriptionValidator();
    }
}
