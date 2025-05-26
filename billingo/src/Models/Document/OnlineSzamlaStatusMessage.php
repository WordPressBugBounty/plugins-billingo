<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Validation\Document\OnlineSzamlaStatusMessageValidator;

/**
 * @property string $validation_result_code
 * @property string $validation_error_code
 * @property string $human_readable_message
 */
class OnlineSzamlaStatusMessage extends BillingoModel
{

    protected function getValidator(): ValidableInterface
    {
        return new OnlineSzamlaStatusMessageValidator();
    }
}
