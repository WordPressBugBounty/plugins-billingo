<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Models\Address;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Models\Partner\PartnerShipping;
use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\Document\DocumentPartnerValidator;

/**
 * @property int $id
 * @property string $name
 * @property Address $address
 * @property array $emails
 * @property string $taxcode
 * @property string $iban
 * @property string $swift
 * @property string $account_number
 * @property string $phone
 * @property string $tax_type
 * @property PartnerShipping $partner_shipping
 */
class DocumentPartner extends BillingoModel
{

    use WithFactory;

    protected array $cast = [
        'address' => Address::class,
        'partner_shipping' => PartnerShipping::class,
    ];

    protected function getValidator(): ValidableInterface
    {
        return new DocumentPartnerValidator();
    }
}
