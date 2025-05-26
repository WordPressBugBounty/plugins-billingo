<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\Document\DocumentCancellationValidator;

/**
 * @property string $cancellation_reason
 * @property string $cancellation_recipients
 */
class DocumentCancellation extends BillingoModel
{

    use WithFactory;

    protected function getValidator(): ValidableInterface
    {
        return new DocumentCancellationValidator();
    }
}
