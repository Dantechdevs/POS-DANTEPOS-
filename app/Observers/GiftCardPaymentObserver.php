<?php

namespace App\Observers;

use App\Services\GiftCardTill;
use App\TransactionPayment;

/**
 * Keeps gift card balances in step with Gift Card payment lines.
 * Runs inside the sale's database transaction, so a bad code or balance rolls the whole sale back.
 * GiftCardTill::sync() is idempotent, so it is safe if more than one hook fires for one payment.
 */
class GiftCardPaymentObserver
{
    public function created(TransactionPayment $payment): void
    {
        if ($payment->method === 'gift_card') {
            GiftCardTill::sync($payment);
        }
    }

    public function updated(TransactionPayment $payment): void
    {
        if ($payment->method === 'gift_card' || $payment->wasChanged('method')) {
            GiftCardTill::sync($payment);
        }
    }

    public function deleted(TransactionPayment $payment): void
    {
        if ($payment->method === 'gift_card') {
            GiftCardTill::sync($payment, true);
        }
    }
}
