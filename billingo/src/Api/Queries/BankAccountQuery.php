<?php

namespace App\Billingo\Api\Queries;

use App\Billingo\Api\BankAccountApi;
use App\Billingo\Service\BillingoQuery;
use App\Billingo\Validation\BankAccount\BankAccountQueryValidator;
use App\Billingo\Validation\Validator;

class BankAccountQuery extends BillingoQuery
{
    public function getOwner(): string
    {
        return BankAccountApi::class;
    }

    protected function getValidator(): Validator
    {
        return new BankAccountQueryValidator();
    }
}
