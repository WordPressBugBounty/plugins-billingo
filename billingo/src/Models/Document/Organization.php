<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Models\Address;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\Document\OrganizationValidator;

/**
 * @property string $name
 * @property string $tax_number
 * @property DocumentBankAccount $bank_account
 * @property Address $address
 * @property boolean $small_taxpayer
 * @property string $ev_number
 * @property string $eu_tax_number
 * @property boolean $cash_settled
 */
class Organization extends BillingoModel
{
    use WithFactory;

    protected array $cast = [
        'bank_account' => DocumentBankAccount::class,
        'address' => Address::class,
    ];

    protected function getValidator(): ValidableInterface
    {
        return new OrganizationValidator();
    }
}
