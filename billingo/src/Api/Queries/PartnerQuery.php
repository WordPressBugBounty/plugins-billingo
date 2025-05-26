<?php

namespace App\Billingo\Api\Queries;

use App\Billingo\Api\PartnerApi;
use App\Billingo\Service\BillingoQuery;
use App\Billingo\Validation\Partner\PartnerQueryValidator;
use App\Billingo\Validation\Validator;

class PartnerQuery extends BillingoQuery
{

    public function getOwner(): string
    {
        return PartnerApi::class;
    }

    protected function getValidator(): Validator
    {
        return new PartnerQueryValidator();
    }
}
