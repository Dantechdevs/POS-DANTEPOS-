<?php

namespace App\Listeners;

use App\Services\GiftCardTill;
use Illuminate\Support\Facades\Log;

/**
 * Some screens remove payments in bulk (no model events) but still fire TransactionPaymentDeleted.
 * sync() is idempotent, so firing twice for the same payment is harmless.
 */
class RefundGiftCardOnPaymentDeleted
{
    public function handle($event): void
    {
        try {
            $payment = data_get($event, 'transactionPayment');

            if ($payment && $payment->method === 'gift_card') {
                GiftCardTill::sync($payment, true);
            }
        } catch (\Throwable $e) {
            Log::error('Gift card refund failed: '.$e->getMessage());
        }
    }
}
