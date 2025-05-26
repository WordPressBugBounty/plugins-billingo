<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Models\BillingoModel;
use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\Document\BankAccountValidator;

/**
 * @property int $id
 * @property string $name
 * @property string $account_number
 * @property string $account_number_iban
 * @property string $swift
 */
class DocumentBankAccount extends BillingoModel
{
    use WithFactory;

    protected function getValidator(): ValidableInterface
    {
        return new BankAccountValidator();
    }
}
