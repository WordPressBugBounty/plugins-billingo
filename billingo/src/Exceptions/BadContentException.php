<?php

namespace App\Billingo\Exceptions;

use App\Billingo\Service\SelfTest;
use Exception;

class BadContentException extends BillingoException
{

    public function __construct(
        private readonly SelfTest $selfTest,
        int $code = 0,
        Exception $previous = null
    ) {
        parent::__construct($code, $previous);
    }
    public function getErrorMessage(): string
    {
        return 'Bad Content';
    }

    public function getSelfTest(): SelfTest
    {
        return $this->selfTest;
    }

    public function getErrors(): ?array
    {
        return $this->selfTest->getErrors();
    }
}
