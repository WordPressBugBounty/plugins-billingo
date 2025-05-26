<?php

namespace App\Billingo\Models\DocumentExport;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\DocumentExport\DocumentExportFilterExtraValidator;

class DocumentExportFilterExtra extends BillingoModel
{
    use WithFactory;

    protected array $cast = [
        'ledger_number' => LedgerNumberInformation::class,
    ];

    protected function getValidator(): ValidableInterface
    {
        return new DocumentExportFilterExtraValidator();
    }
}
