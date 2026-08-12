<?php

namespace App\Billingo\Enums;

enum BillingoErrorEnum: string
{
    case EMPTY_OBJECT = 'Empty Object';
    case VALIDATION_FAILED = 'Validation Failed';
    case BAD_CONTENT = 'Bad Content';
    case BAD_API_CONTEXT = 'Bad Api Context';
    case NETWORK_ERROR = 'Network Error';
}
