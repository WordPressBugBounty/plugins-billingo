<?php

namespace App\Billingo\Models\DocumentBlock;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\DocumentBlock\DocumentBlockSchemaValidator;

/**
 * @property int $id
 * @property string $name
 * @property string $prefix
 * @property string $type
 * @property string|null $custom_field1
 * @property string|null $custom_field2
 * @property int $template_id
 * @property string $pic_path
 * @property int $normal_notification_id
 * @property int $proforma_notification_id
 * @property bool $generated
 * @property string $invoice_number_format
 * @property string|null $sender_email
 * @property string $sender_name
 * @property string|null $bcc_email
 * @property bool $create_invoice_from_paid_proforma
 * @property bool $fulfillment_date_from_proforma_payment_date
 * @property bool $attach_xml_to_invoice_pdf
 * @property bool $advance_paid_instant
 * @property string $financial_fulfillment_highlight
 * @property bool $entitlements_in_document_comment
 */
class DocumentBlockSchema extends BillingoModel
{
    use WithFactory;

    protected function getValidator(): ValidableInterface
    {
        return new DocumentBlockSchemaValidator();
    }
}
