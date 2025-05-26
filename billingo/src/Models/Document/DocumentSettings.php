<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Models\Document\DocumentInstantPaymentRequest;
use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\Document\DocumentSettingsValidator;

/**
 * @property boolean $mediated_service
 * @property boolean $without_financial_fulfillment
 * @property string $online_payment
 * @property boolean $should_send_email
 * @property string $round
 * @property string $dont_send_to_nav_reason
 * @property string $order_number
 * @property integer $place_id
 * @property boolean $instant_payment
 * @property string $selected_type
 * @property null|DocumentInstantPaymentRequest $instant_payment_request
 */
class DocumentSettings extends BillingoModel
{

    use WithFactory;

    protected array $cast = [
        'instant_payment_request' => DocumentInstantPaymentRequest::class,
    ];

    protected function getValidator(): ValidableInterface
    {
        return new DocumentSettingsValidator();
    }
}
