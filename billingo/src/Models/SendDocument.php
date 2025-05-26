<?php

namespace App\Billingo\Models;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Validation\SendDocumentValidator;

/**
 * @property string[] $emails
 */
class SendDocument extends BillingoModel
{

    protected function getValidator(): ValidableInterface
    {
        return new SendDocumentValidator();
    }
}
