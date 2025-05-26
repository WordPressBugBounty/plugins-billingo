<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Validation\Document\DocumentItemDataValidator;

class DocumentItemData extends BillingoModel
{

    protected function getValidator(): ValidableInterface
    {
        return new DocumentItemDataValidator();
    }
}
