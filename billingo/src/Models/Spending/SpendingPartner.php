<?php

namespace App\Billingo\Models\Spending;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\Address;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Validation\Spending\SpendingPartnerValidator;

/**
 * @property int $id
 * @property string $name
 * @property string $tax_code
 * @property Address $address
 * @property string $iban
 * @property string $swift
 * @property string $account_number
 * @property string $phone
 * @property string $internal_comment
 * @property string $group_member_tax_number
 */
class SpendingPartner extends BillingoModel
{

    protected array $cast = [
        'address' => Address::class,
    ];

    protected function getValidator(): ValidableInterface
    {
        return new SpendingPartnerValidator();
    }
}
