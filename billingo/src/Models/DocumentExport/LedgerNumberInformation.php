<?php

namespace App\Billingo\Models\DocumentExport;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\DocumentExport\LedgerNumberInformationValidator;

class LedgerNumberInformation extends BillingoModel
{
    use WithFactory;

    protected function getValidator(): ValidableInterface
    {
        return new LedgerNumberInformationValidator();
    }
}
