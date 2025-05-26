<?php

namespace App\Billingo\Models\DocumentExport;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Validation\DocumentExport\DocumentExportIdValidator;

class DocumentExportId extends BillingoModel
{

    protected function getValidator(): ValidableInterface
    {
        return new DocumentExportIdValidator();
    }
}
