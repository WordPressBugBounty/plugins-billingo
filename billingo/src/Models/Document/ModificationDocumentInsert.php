<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Validation\Document\ModificationDocumentInsertValidator;

/**
 * @property string $due_date
 * @property string $comment
 * @property string $payment_method
 * @property bool $without_financial_fulfillment
 * @property array $items
 */
class ModificationDocumentInsert extends BillingoModel
{

    protected array $cast = [
        'items' => [DocumentItemData::class],
    ];

    protected function getValidator(): ValidableInterface
    {
        return new ModificationDocumentInsertValidator();
    }
}
