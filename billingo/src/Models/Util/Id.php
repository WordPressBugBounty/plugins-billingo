<?php

namespace App\Billingo\Models\Util;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Validation\Util\IdValidator;

/**
 * @property int $id
 * @property int $legacy_id
 */

class Id extends BillingoModel
{

    protected function getValidator(): ValidableInterface
    {
        return new IdValidator();
    }
}
