<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\Document\AncestorValidator;

/**
 * @property int $id
 * @property string $invoice_number
 */
class DocumentAncestor extends BillingoModel
{

    use WithFactory;

    protected function getValidator(): ValidableInterface
    {
        return new AncestorValidator();
    }
}
