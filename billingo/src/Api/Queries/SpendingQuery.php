<?php

namespace App\Billingo\Api\Queries;

use App\Billingo\Api\SpendingApi;
use App\Billingo\Service\BillingoQuery;
use App\Billingo\Validation\Spending\SpendingQueryValidator;
use App\Billingo\Validation\Validator;

class SpendingQuery extends BillingoQuery
{

    public function getOwner(): string
    {
        return SpendingApi::class;
    }

    protected function getValidator(): Validator
    {
        return new SpendingQueryValidator();
    }
}
