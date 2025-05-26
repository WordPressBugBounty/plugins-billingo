<?php

namespace App\Billingo\Factories;

use App\Billingo\Enums\CorrectionTypeEnum;
use App\Billingo\Enums\CurrencyEnum;
use App\Billingo\Enums\Document\LanguageEnum;
use App\Billingo\Enums\Document\NotificationStatusEnum;
use App\Billingo\Enums\Document\TypeEnum;
use App\Billingo\Enums\OnlineSzamlaStatusEnum;
use App\Billingo\Enums\PaymentMethodEnum;
use App\Billingo\Enums\PaymentStatusEnum;
use App\Billingo\Models\Discount;
use App\Billingo\Models\Document\DocumentAncestor;
use App\Billingo\Models\Document\DocumentInstantPaymentRequest;
use App\Billingo\Models\Document\DocumentItem;
use App\Billingo\Models\Document\DocumentPartner;
use App\Billingo\Models\Document\DocumentProductData;
use App\Billingo\Models\Document\DocumentSettings;
use App\Billingo\Models\Document\DocumentSummary;
use App\Billingo\Models\Document\Organization;
use App\Billingo\Models\Partner\Partner;

class DocumentFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'id' => $this->faker->numberBetween(1, 1000),
            'invoice_number' => $this->faker->regexify('202[0-9]-[0-9]{6}'),
            'type' => $this->faker->randomElement(getEnumValues(TypeEnum::class)),
            'cancelled' => $this->faker->boolean(),
            'block_id' => $this->faker->numberBetween(1, 10),
            'payment_status' => $this->faker->randomElement(getEnumValues(PaymentStatusEnum::class)),
            'payment_method' => $this->faker->randomElement(getEnumValues(PaymentMethodEnum::class)),
            'gross_total' => $this->faker->randomFloat(2, 100, 10000),
            'currency' => $this->faker->randomElement(getEnumValues(CurrencyEnum::class)),
            'conversion_rate' => $this->faker->randomFloat(2, 1, 100),
            'invoice_date' => $this->faker->date(),
            'fulfillment_date' => $this->faker->date(),
            'due_date' => $this->faker->date(),
            'paid_date' => $this->faker->date(),
            'organization' => Organization::factory(),
            'partner' => Partner::factory(),
            'document_partner' => DocumentPartner::factory(),
            'electronic' => $this->faker->boolean(),
            'comment' => $this->faker->sentence(),
            'tags' => [$this->faker->word(), $this->faker->word()],
            'notification_status' => $this->faker->randomElement(getEnumValues(NotificationStatusEnum::class)),
            'language' => $this->faker->randomElement(getEnumValues(LanguageEnum::class)),
            'items' => [DocumentItem::factory(), DocumentItem::factory()],
            'summary' => DocumentSummary::factory(),
            'settings' => DocumentSettings::factory(),
            'online_szamla_status' => $this->faker->randomElement(getEnumValues(OnlineSzamlaStatusEnum::class)),
            'related_documents' => [DocumentAncestor::factory(), DocumentAncestor::factory()],
            'discount' => Discount::factory(),
            'correction_type' => $this->faker->randomElement(getEnumValues(CorrectionTypeEnum::class)),
            'recurring_id' => $this->faker->numberBetween(1, 100),
        ];
    }

    public function insert(): array
    {
        return [
            'vendor_id' =>  $this->faker->optional()->word(),
            'partner_id' => $this->faker->numberBetween(1, 1000),
            'block_id' => $this->faker->numberBetween(1, 100),
            'bank_account_id' => $this->faker->optional()->numberBetween(1, 1000),
            'type' => $this->faker->randomElement(getEnumValues(TypeEnum::class)),
            'fulfillment_date' => $this->faker->dateTimeBetween('2010-01-01')->format('Y-m-d'),
            'due_date' => $this->faker->dateTimeBetween('2010-01-01')->format('Y-m-d'),
            'payment_method' => $this->faker->randomElement(getEnumValues(PaymentMethodEnum::class)),
            'language' => $this->faker->randomElement(getEnumValues(LanguageEnum::class)),
            'currency' => $this->faker->randomElement(getEnumValues(CurrencyEnum::class)),
            'conversion_rate' => $this->faker->optional(0.9, 1)->randomFloat(2, 0.5, 1.5),
            'electronic' => $this->faker->optional(0.9, false)->boolean(),
            'paid' => $this->faker->optional(0.9, false)->boolean(),
            'items' => [$this->faker
                ->randomElement([
                    DocumentItem::factory(),
                    DocumentProductData::factory()])],
            'comment' => $this->faker->optional()->sentence(),
            'settings' => $this->faker->boolean(40)
                ? DocumentSettings::factory()
                : null,
            'advance_invoice' => [$this->faker->numberBetween(1, 1000)],
            'discount' => $this->faker->boolean(40)
                ? Discount::factory()
                : null,
            'instant_payment' => $this->faker->optional()->boolean(),
            'instant_payment_request' => $this->faker->boolean(90)
                ? DocumentInstantPaymentRequest::factory()
                : null,
        ];
    }
}
