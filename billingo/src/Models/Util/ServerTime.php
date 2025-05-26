<?php

namespace App\Billingo\Models\Util;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Validation\Util\ServerTimeValidator;

/**
 * @property int $epoch
 * @property string $formatted
 * @property string $w3c
 * @property string $timezone
 */
class ServerTime extends BillingoModel
{

    protected function getValidator(): ValidableInterface
    {
        return new ServerTimeValidator();
    }
}
