<?php

namespace App\Billingo\Models\Organization;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Models\Subscription;
use App\Billingo\Validation\Organization\OrganizationDataValidator;

/**
 * @property string $tax_code
 * @property Subscription $subscription
 */
class OrganizationData extends BillingoModel
{

    protected array $cast = [
        'subscription' => Subscription::class,
    ];

    protected function getValidator(): ValidableInterface
    {
        return new OrganizationDataValidator();
    }
}
