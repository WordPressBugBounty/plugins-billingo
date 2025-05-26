<?php

namespace App\Billingo\Validation\Document;

use App\Billingo\Enums\CorrectionTypeEnum;
use App\Billingo\Enums\CurrencyEnum;
use App\Billingo\Enums\Document\NotificationStatusEnum;
use App\Billingo\Enums\Document\TypeEnum;
use App\Billingo\Enums\Document\LanguageEnum;
use App\Billingo\Enums\OnlineSzamlaStatusEnum;
use App\Billingo\Enums\PaymentMethodEnum;
use App\Billingo\Enums\PaymentStatusEnum;
use App\Billingo\Models\Discount;
use App\Billingo\Models\Document\DocumentAncestor;
use App\Billingo\Models\Document\DocumentItem;
use App\Billingo\Models\Document\Organization;
use App\Billingo\Models\Document\DocumentSettings;
use App\Billingo\Models\Document\DocumentSummary;
use App\Billingo\Models\Partner\Partner;
use App\Billingo\Models\Document\DocumentPartner as DocumentPartner;
use App\Billingo\Validation\Validator;

final class DocumentValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'id' => ['optional', 'integer'],
            'invoice_number' => ['optional', 'string'],
            'type' => ['optional', ['in', getEnumValues(TypeEnum::class)]],
            'cancelled' => ['optional', 'boolean'],
            'block_id' => ['optional', 'integer'],
            'payment_status' => ['optional', ['in', getEnumValues(PaymentStatusEnum::class)]],
            'payment_method' => ['optional', ['in', getEnumValues(PaymentMethodEnum::class)]],
            'gross_total' => ['optional', 'numeric'],
            'currency' => ['optional', ['in', getEnumValues(CurrencyEnum::class)]],
            'conversion_rate' => ['optional', 'numeric'],
            'invoice_date' => ['optional', ['dateFormat', 'Y-m-d']],
            'fulfillment_date' => ['optional', ['dateFormat', 'Y-m-d']],
            'due_date' => ['optional', ['dateFormat', 'Y-m-d']],
            'paid_date' => ['optional', ['dateFormat', 'Y-m-d']],
            'organization' => ['optional', ['instanceOf', Organization::class]],
            'partner' => ['optional', ['instanceOf', Partner::class]],
            'document_partner' => ['optional', ['instanceOf', DocumentPartner::class]],
            'electronic' => ['optional', 'boolean'],
            'comment' => ['optional', 'string'],
            'tags' => ['optional', 'array'],
            'tags.*' => ['string'],
            'notification_status' => ['optional', ['in', getEnumValues(NotificationStatusEnum::class)]],
            'language' => ['optional', ['in', getEnumValues(LanguageEnum::class)]],
            'items' => ['optional', 'array'],
            'items.*' => [['instanceOf', DocumentItem::class]],
            'summary' => ['optional', ['instanceOf', DocumentSummary::class]],
            'settings' => ['optional', ['instanceOf', DocumentSettings::class]],
            'online_szamla_status' => ['optional', ['in', getEnumValues(OnlineSzamlaStatusEnum::class)]],
            'related_documents' => ['optional', 'array'],
            'related_documents.*' => [['instanceOf', DocumentAncestor::class]],
            'discount' => ['optional', ['instanceOf', Discount::class]],
            'correction_type' => ['optional', ['in', getEnumValues(CorrectionTypeEnum::class)]],
            'recurring_id' => ['optional', 'integer'],
        ];
    }
}
