<?php

namespace App\Billingo\Models\Document;

use App\Billingo\Models\BillingoModel;
use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\Document\DocumentSummaryValidator;

/**
 * @property float $net_amount
 * @property float $net_amount_local
 * @property float $gross_amount_local
 * @property float $vat_amount
 * @property float $vat_amount_local
 * @property array $vat_rate_summary
 */
class DocumentSummary extends BillingoModel
{
    use WithFactory;

    protected array $cast = [
        'vat_rate_summary' => [DocumentVatRateSummary::class],
    ];

    protected function getValidator(): ValidableInterface
    {
        return new DocumentSummaryValidator();
    }
}
