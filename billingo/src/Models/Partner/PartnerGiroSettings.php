<?php

namespace App\Billingo\Models\Partner;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\Partner\PartnerGiroSettingsValidator;

/**
 * @property bool $giro_default_settings
 * @property bool $giro_payment_request_enabled
 * @property bool $giro_different_amount_allowed
 */
class PartnerGiroSettings extends BillingoModel
{
    use WithFactory;

    protected function getValidator(): ValidableInterface
    {
        return new PartnerGiroSettingsValidator();
    }
}
