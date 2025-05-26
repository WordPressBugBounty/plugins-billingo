<?php

namespace App\Billingo\Api\Queries;

use App\Billingo\Api\DocumentApi;
use App\Billingo\Service\BillingoQuery;
use App\Billingo\Validation\Document\DocumentQueryValidator;
use App\Billingo\Validation\Validator;

class DocumentQuery extends BillingoQuery
{

    public function getOwner(): string
    {
        return DocumentApi::class;
    }

    public function getValidator(): Validator
    {
        return new DocumentQueryValidator();
    }
}
