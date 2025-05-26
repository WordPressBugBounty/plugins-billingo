<?php

namespace App\Billingo\Models;

use App\Billingo\Contracts\ValidableInterface;
use App\Billingo\Models\BillingoModel;
use App\Billingo\Validation\PaymentHistoryValidator;

/**
 * @property string $date
 * @property float $price
 * @property string $payment_method
 * @property string|null $voucher_number
 * @property float|null $conversion_rate
 */
class PaymentHistory extends BillingoModel
{

    protected function getValidator(): ValidableInterface
    {
        return new PaymentHistoryValidator();
    }
}
