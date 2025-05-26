<?php

namespace App\Billingo\Models\DocumentExport;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\DocumentExport\CreateDocumentExportValidator;

class CreateDocumentExport extends BillingoModel
{
    use WithFactory;

    protected array $cast = [
        'filter_extra' => DocumentExportFilterExtra::class,
    ];

    protected function getValidator(): ValidableInterface
    {
        return new CreateDocumentExportValidator();
    }
}
