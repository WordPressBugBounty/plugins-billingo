<?php

namespace App\Billingo\Exceptions;

use Exception;

abstract class BillingoException extends Exception
{

    public function __construct(int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($this->getErrorMessage(), $code, $previous);
    }

    abstract public function getErrorMessage(): string;

}
