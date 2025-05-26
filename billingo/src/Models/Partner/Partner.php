<?php

namespace App\Billingo\Models\Partner;

use App\Billingo\Models\Address;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\Partner\PartnerValidator;

/**
 * @property int $id
 * @property string $name
 * @property Address $address
 * @property string[] $emails
 * @property string $taxcode
 * @property string $iban
 * @property string $swift
 * @property string $account_number
 * @property string $phone
 * @property string $general_ledger_number
 * @property string $tax_type
 * @property PartnerCustomBillingoSettings $custom_billing_settings
 * @property string $group_member_tax_number
 * @property PartnerGiroSettings|null $giro_settings
 * @property PartnerShipping|null $partner_shipping
 * @property string|null $internal_comment
 * @property string|null $partner_show_type
 */
class Partner extends BillingoModel
{
    use WithFactory;

    protected array $cast = [
        'address' => Address::class,
        'custom_billing_settings' => PartnerCustomBillingoSettings::class,
        'giro_settings' => PartnerGiroSettings::class,
        'partner_shipping' => PartnerShipping::class,
    ];

    protected function getValidator(): ValidableInterface
    {
        return new PartnerValidator();
    }
}
