<?php

namespace App\Billingo\Exceptions;

use Exception;

class ValidationException extends BillingoException
{

    public function __construct(
        private readonly array $errors,
        int $code = 0,
        Exception $previous = null
    ) {
        parent::__construct($code, $previous);
    }

    public function getErrorMessage(): string
    {
        return 'Validation Failed: ' . json_encode($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
