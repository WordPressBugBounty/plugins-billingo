<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Validation\Document\InvoiceSettingsValidator;

/**
 * @property string $document_type
 * @property string $fulfillment_date
 * @property string $due_date
 * @property string $document_format
 * @property string $comment
 */
class InvoiceSettings extends BillingoModel
{

    protected function getValidator(): ValidableInterface
    {
        return new InvoiceSettingsValidator();
    }
}
