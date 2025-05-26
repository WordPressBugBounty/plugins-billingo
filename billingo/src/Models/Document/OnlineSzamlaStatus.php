<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Validation\Document\OnlineSzamlaStatusValidator;

/**
 * @property string $transaction_id
 * @property string $status
 * @property OnlineSzamlaStatusMessage $messages
 */
class OnlineSzamlaStatus extends BillingoModel
{

    protected array $cast = [
        'messages' => [OnlineSzamlaStatusMessage::class],
    ];

    protected function getValidator(): ValidableInterface
    {
        return new OnlineSzamlaStatusValidator();
    }
}
