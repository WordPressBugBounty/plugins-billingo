<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\Document\DocumentInstantPaymentRequestValidator;

/**
 * @property string $debtor_bank_account
 * @property bool $different_amount_allowed
 * @property bool $update_partner_payment_details
 */
class DocumentInstantPaymentRequest extends BillingoModel
{
    use WithFactory;

    protected function getValidator(): ValidableInterface
    {
        return new DocumentInstantPaymentRequestValidator();
    }
}
