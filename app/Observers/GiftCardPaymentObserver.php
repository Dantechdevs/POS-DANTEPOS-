<?php

namespace App\Observers;

use App\Services\GiftCardService;
use App\TransactionPayment;

/**
 * Runs inside the sale's database transaction, so a bad gift card code or balance
 * rolls the whole sale back and nothing is charged.
 */
class GiftCardPaymentObserver
{
    public function created(TransactionPayment $payment): void
    {
        $svc = app(GiftCardService::class);

        if ($svc->isGiftPayment($payment)) {
            $svc->syncPayment($payment);
        }
    }

    public function updated(TransactionPayment $payment): void
    {
        $svc = app(GiftCardService::class);

        if ($svc->method() !== null) {
            $svc->syncPayment($payment);
        }
    }

    public function deleted(TransactionPayment $payment): void
    {
        $svc = app(GiftCardService::class);

        if ($svc->method() !== null) {
            $svc->syncPayment($payment, true);
        }
    }
}
