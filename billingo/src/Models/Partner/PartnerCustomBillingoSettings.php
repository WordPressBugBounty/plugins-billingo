<?php

namespace App\Billingo\Models\Partner;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Models\Discount;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\Partner\PartnerCustomBillingSettingsValidator;

/**
 * @property string $payment_method
 * @property string $document_form
 * @property int $due_days
 * @property string $document_currency
 * @property string $template_language_code
 * @property Discount $discount
 */
class PartnerCustomBillingoSettings extends BillingoModel
{
    use WithFactory;

    protected array $cast = [
        'discount' => Discount::class,
    ];

    protected function getValidator(): ValidableInterface
    {
        return new PartnerCustomBillingSettingsValidator();
    }
}
