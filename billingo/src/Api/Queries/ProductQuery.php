<?php

namespace App\Billingo\Api\Queries;

use App\Billingo\Api\ProductApi;
use App\Billingo\Service\BillingoQuery;
use App\Billingo\Validation\Product\ProductQueryValidator;
use App\Billingo\Validation\Validator;

class ProductQuery extends BillingoQuery
{

    public function getOwner(): string
    {
        return ProductApi::class;
    }

    protected function getValidator(): Validator
    {
        return new ProductQueryValidator();
    }
}
