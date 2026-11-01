<?php

namespace App\Observers;

use App\Services\GiftCardService;
use App\TransactionPayment;

class GiftCardPaymentObserver
{
    private function applies(TransactionPayment $tp): bool
    {
        return $tp->method === 'gift_card' && empty($tp->is_return) && ! empty($tp->transaction_id);
    }

    public function created(TransactionPayment $tp)
    {
        if ($this->applies($tp)) {
            GiftCardService::redeem($tp, (float) $tp->amount);
        }
    }

    public function updated(TransactionPayment $tp)
    {
        if (! $this->applies($tp) || ! $tp->wasChanged('amount')) {
            return;
        }
        $diff = round((float) $tp->amount - (float) $tp->getOriginal('amount'), 4);
        if ($diff > 0) {
            GiftCardService::redeem($tp, $diff);
        } elseif ($diff < 0) {
            GiftCardService::restore($tp, -$diff, 'Sale payment reduced');
        }
    }

    public function deleted(TransactionPayment $tp)
    {
        if ($this->applies($tp)) {
            GiftCardService::restore($tp, (float) $tp->amount, 'Sale payment deleted');
        }
    }
}
