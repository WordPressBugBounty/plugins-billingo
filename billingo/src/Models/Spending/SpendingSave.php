<?php

namespace App\Billingo\Models\Spending;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Traits\WithFactory;
use App\Billingo\Validation\Spending\SpendingSaveValidator;

/**
 * @property string $currency
 * @property float $conversion_rate
 * @property float $total_gross
 * @property float $total_gross_huf
 * @property float $total_vat_amount
 * @property float $total_vat_amount_huf
 * @property string $fulfillment_date
 * @property string $paid_at
 * @property string $category
 * @property string $comment
 * @property string $invoice_number
 * @property string $invoice_date
 * @property string $due_date
 * @property string $payment_method
 * @property int $partner_id
 */
class SpendingSave extends BillingoModel
{
    use WithFactory;

    protected function getValidator(): ValidableInterface
    {
        return new SpendingSaveValidator();
    }
}
