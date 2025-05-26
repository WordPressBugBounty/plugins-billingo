<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Models\BillingoModel;
use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\Document\DocumentVatRateSummaryValidator;

/**
 * @property string $vat_name
 * @property float $vat_percentage
 * @property float $vat_rate_net_amount
 * @property float $vat_rate_vat_amount
 * @property float $vat_rate_vat_amount_local
 * @property float $vat_rate_gross_amount
 */
class DocumentVatRateSummary extends BillingoModel
{
    use WithFactory;

    protected function getValidator(): ValidableInterface
    {
        return new DocumentVatRateSummaryValidator();
    }
}
