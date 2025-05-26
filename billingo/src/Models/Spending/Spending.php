<?php

namespace App\Billingo\Models\Spending;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Validation\Spending\SpendingValidator;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $category
 * @property string|null $paid_at
 * @property string $fulfillment_date
 * @property SpendingPartner $partner
 * @property string $invoice_number
 * @property string $currency
 * @property float $conversion_rate
 * @property float $total_gross
 * @property float $total_gross_local
 * @property float $total_vat_amount
 * @property float $total_vat_amount_local
 * @property string $invoice_date
 * @property string $due_date
 * @property string $payment_method
 * @property string|null $comment
 */
class Spending extends BillingoModel
{

    protected array $cast = [
        'partner' => SpendingPartner::class,
    ];

    protected function getValidator(): ValidableInterface
    {
        return new SpendingValidator();
    }
}
