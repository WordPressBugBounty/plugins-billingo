<?php

namespace App\Billingo\Models\DocumentBlock;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\DocumentBlock\DocumentBlockValidator;

/**
 * @property int $id
 * @property string $name
 * @property string $prefix
 * @property string $custom_field1
 * @property string $custom_field2
 * @property string $type
 */
class DocumentBlock extends BillingoModel
{
    use WithFactory;

    protected function getValidator(): ValidableInterface
    {
        return new DocumentBlockValidator();
    }
}
