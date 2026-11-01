<?php

namespace App\Services;

use App\Models\MpesaTransaction;

class MpesaPaymentGuard
{
    /**
     * Verify that a confirmed STK payment exists for this checkout id, covers
     * the amount being recorded, and has not been used before. Marks it
     * claimed so the same M-Pesa receipt cannot pay two debts.
     * Call inside an open DB transaction; throws on any problem.
     */
    public static function claimForDuePayment(string $checkoutRequestId, float $amount, int $businessId): MpesaTransaction
    {
        if ($checkoutRequestId === '') {
            throw new \Exception('M-Pesa payment not confirmed. Send the STK Push and wait for the customer to pay before saving.');
        }

        $mt = MpesaTransaction::where('checkout_request_id', $checkoutRequestId)
            ->where(function ($q) use ($businessId) {
                $q->where('business_id', $businessId)->orWhereNull('business_id');
            })
            ->lockForUpdate()
            ->first();

        if (! $mt) {
            throw new \Exception('M-Pesa payment not found.');
        }
        if ($mt->status !== 'success') {
            throw new \Exception('M-Pesa payment is not confirmed (status: '.$mt->status.').');
        }
        if (! empty($mt->claimed_at) || ! empty($mt->transaction_id)) {
            throw new \Exception('This M-Pesa payment ('.$mt->mpesa_receipt_number.') has already been used.');
        }
        // STK amounts are whole shillings; allow for that rounding.
        if ($amount > (float) $mt->amount + 0.99) {
            throw new \Exception('Amount entered (KSh '.number_format($amount, 2).') is more than the M-Pesa payment received (KSh '.number_format($mt->amount, 2).').');
        }

        $mt->update(['claimed_at' => now()]);

        return $mt;
    }
}
