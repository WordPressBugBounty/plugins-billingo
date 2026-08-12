<?php

namespace App\Billingo\Models\Partner;

use App\Billingo\Models\Address;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\Partner\PartnerShippingValidator;

/**
 * @property boolean $match
 * @property string $name
 * @property string $mode
 * @property Address $address
 */
class PartnerShipping extends BillingoModel
{
    use WithFactory;

    protected array $cast = [
        'address' => Address::class,
    ];

    protected function getValidator(): ValidableInterface
    {
        return new PartnerShippingValidator();
    }

    /**
     * Amikor a rendelésnek nincs külön szállítási címe (mode: "none"), a Billingo API egy
     * üres cím-placeholdert küld vissza (csak country_code kitöltve, post_code/city/address
     * üresen) — ezt nem szabad hiányos, hibás címként validálnunk. Ha az address mindhárom
     * érdemi mezője üres, egyszerűen kihagyjuk a cím-objektum létrehozását (null marad).
     */
    public function fromArray(array $data): self
    {
        if (isset($data['address']) && is_array($data['address'])) {
            $meaningfulFields = array_filter([
                $data['address']['post_code'] ?? '',
                $data['address']['city'] ?? '',
                $data['address']['address'] ?? '',
            ]);

            if (empty($meaningfulFields)) {
                unset($data['address']);
            }
        }

        return parent::fromArray($data);
    }
}
