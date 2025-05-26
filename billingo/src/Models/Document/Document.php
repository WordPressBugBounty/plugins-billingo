<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Models\Discount;
use App\Billingo\Models\Document\DocumentPartner as DocumentPartner;
use App\Billingo\Models\Partner\Partner;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\Document\DocumentValidator;

/**
 * * @property int $id
 * * @property string $invoice_number
 * * @property string $type
 * * @property string $correction_type
 * * @property bool $cancelled
 * * @property int $block_id
 * * @property string $payment_status
 * * @property string $payment_method
 * * @property float $gross_total
 * * @property string $currency
 * * @property float $conversion_rate
 * * @property string $invoice_date
 * * @property string $fulfillment_date
 * * @property string $due_date
 * * @property string|null $paid_date
 * * @property Organization $organization
 * * @property Partner $partner
 * * @property DocumentPartner $document_partner
 * * @property bool $electronic
 * * @property string $comment
 * * @property array $tags
 * * @property string $notification_status
 * * @property string $language
 * * @property array $items
 * * @property array $settings
 * * @property string $online_szamla_status
 * * @property float|null $discount
 * * @property int|null $recurring_id
 * * @property DocumentSummary $summary
 * * @property array $related_documents
 */
class Document extends BillingoModel
{

    use WithFactory;

    protected array $cast = [
        'organization' => Organization::class,
        'partner' => Partner::class,
        'document_partner' => DocumentPartner::class,
        'items' => [DocumentItem::class],
        'summary' => DocumentSummary::class,
        'settings' => DocumentSettings::class,
        'related_documents' => [DocumentAncestor::class],
        'discount' => Discount::class,
    ];

    protected function getValidator(): ValidableInterface
    {
        return new DocumentValidator();
    }
}
