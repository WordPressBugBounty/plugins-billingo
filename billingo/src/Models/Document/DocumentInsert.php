<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Models\Discount;
use App\Billingo\Validation\Document\DocumentInsertValidator;

/**
 * @property string $vendor_id
 * @property int $partner_id
 * @property int $block_id
 * @property int $bank_account_id
 * @property string $type
 * @property string $fulfillment_date
 * @property string $due_date
 * @property string $payment_method
 * @property string $language
 * @property string $currency
 * @property float $conversion_rate
 * @property bool $electronic
 * @property bool $paid
 * @property array $items
 * @property string $comment
 * @property DocumentSettings $settings
 * @property int[] $advance_invoice
 * @property Discount $discount
 * @property bool $instant_payment
 * @property DocumentInstantPaymentRequest|null $instant_payment_request
 */
class DocumentInsert extends BillingoModel
{
    protected array $cast = [
        'items' => [DocumentItem::class],
        'settings' => DocumentSettings::class,
        'discount' => Discount::class,
    ];

    protected function getValidator(): ValidableInterface
    {
        return new DocumentInsertValidator();
    }
}
