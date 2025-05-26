<?php

namespace App\Billingo\Factories;

use App\Billingo\Enums\DocumentBlockTypeEnum;
use App\Billingo\Enums\FinancialFulfillmentHighlightEnum;
use App\Billingo\Enums\invoiceNumberFormatEnum;

class DocumentBlockSchemaFactory extends BaseFactory
{

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'prefix' => $this->faker->word(),
            'type' => $this->faker->randomElement(getEnumValues(DocumentBlockTypeEnum::class)),
            'invoice_number_format' =>
                $this->faker->optional()->randomElement(getEnumValues(InvoiceNumberFormatEnum::class)),
            'sender_email' => $this->faker->optional()->email(),
            'sender_name' => $this->faker->optional()->name(),
            'bcc_email' => $this->faker->optional()->email(),
            'create_invoice_from_paid_proforma' => $this->faker->optional()->boolean(),
            'fulfillment_date_from_proforma_payment_date' => $this->faker->optional()->boolean(),
            'attach_xml_to_invoice_pdf' => $this->faker->optional()->boolean(),
            'advance_paid_instant' => $this->faker->optional()->boolean(),
            'financial_fulfillment_highlight' =>
                $this->faker->optional()->randomElement(getEnumValues(FinancialFulfillmentHighlightEnum::class)),
            'entitlements_in_document_comment' => $this->faker->optional()->boolean(),
        ];
    }
}
