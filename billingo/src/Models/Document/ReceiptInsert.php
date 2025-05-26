<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Validation\Document\Insert\InsertReceiptValidator;

/**
 * @property string $vendor_id
 * @property int $partner_id
 * @property string $name
 * @property string[] $emails
 * @property int $block_id
 * @property string $type
 * @property string $payment_method
 * @property string $currency
 * @property float $conversion_rate
 * @property bool $electronic
 * @property array $items
 */
class ReceiptInsert extends BillingoModel
{

    protected array $cast = [
        'items' => [ReceiptProductData::class],
    ];

    protected function getValidator(): ValidableInterface
    {
        return new InsertReceiptValidator();
    }
}
