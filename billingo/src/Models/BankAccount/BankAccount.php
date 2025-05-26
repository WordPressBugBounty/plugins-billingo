<?php

namespace App\Billingo\Models\BankAccount;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\BankAccount\BankAccountValidator;

/**
 * @property int $id
 * @property string $name
 * @property string $account_number
 * @property string $account_number_iban
 * @property string $swift
 * @property string $currency
 */
class BankAccount extends BillingoModel
{
    use WithFactory;

    protected function getValidator(): ValidableInterface
    {
        return new BankAccountValidator();
    }
}
