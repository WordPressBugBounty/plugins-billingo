<?php

namespace App\Billingo\Models\Currency;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Validation\Currency\ConversationRateValidator;

/**
 * @property string $from_currency
 * @property string $to_currency
 * @property float $conversation_rate
 * @property string $date
 */
class ConversationRate extends BillingoModel
{

    protected function getValidator(): ValidableInterface
    {
        return new ConversationRateValidator();
    }
}
