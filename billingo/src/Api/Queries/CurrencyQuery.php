<?php

namespace App\Billingo\Api\Queries;

use App\Billingo\Api\CurrencyApi;
use App\Billingo\Service\BillingoQuery;
use App\Billingo\Validation\Currency\CurrencyQueryValidator;
use App\Billingo\Validation\Validator;

class CurrencyQuery extends BillingoQuery
{
    protected string $callableMethod = 'getConversationRate';

    public function getOwner(): string
    {
        return CurrencyApi::class;
    }

    protected function getValidator(): Validator
    {
        return new CurrencyQueryValidator();
    }
}
