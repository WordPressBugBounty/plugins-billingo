<?php

namespace App\Billingo\Api\Queries;

use App\Billingo\Api\DocumentBlockApi;
use App\Billingo\Service\BillingoQuery;
use App\Billingo\Validation\DocumentBlock\DocumentBlockQueryValidator;
use App\Billingo\Validation\Validator;

class DocumentBlockQuery extends BillingoQuery
{

    public function getOwner(): string
    {
        return DocumentBlockApi::class;
    }

    protected function getValidator(): Validator
    {
        return new DocumentBlockQueryValidator();
    }
}
