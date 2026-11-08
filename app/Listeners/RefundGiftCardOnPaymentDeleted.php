<?php

namespace App\Listeners;

use App\Services\GiftCardService;
use Illuminate\Support\Facades\Log;

/**
 * Some screens remove payments in bulk (no model events) but still fire TransactionPaymentDeleted.
 * The refund is idempotent, so firing twice for the same payment is harmless.
 */
class RefundGiftCardOnPaymentDeleted
{
    public function handle($event): void
    {
        try {
            $svc = app(GiftCardService::class);
            $payment = data_get($event, 'transactionPayment');

            if ($payment && $svc->method() !== null) {
                $svc->syncPayment($payment, true);
            }
        } catch (\Throwable $e) {
            Log::error('Gift card refund failed: '.$e->getMessage());
        }
    }
}
