<?php

namespace App\Billingo\Exceptions;

class EmptyObjectException extends BillingoException
{

    public function getErrorMessage(): string
    {
        return 'Empty Object';
    }
}
