<?php

namespace App\Services;

use App\Models\MpesaTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Checks an M-Pesa payment line on a sale. Call inside the open DB transaction.
 * Two ways to pay: a confirmed STK push, or a till payment identified by the
 * M-Pesa code (unverified unless it matches a confirmed record).
 */
class MpesaSaleGuard
{
    public static function inspect(array $payment, $transaction, float $amount): array
    {
        if (($payment['method'] ?? '') !== 'mpesa'
            || ($transaction->type ?? '') !== 'sell'
            || ! empty($payment['is_return'])
            || $amount <= 0) {
            return [];
        }

        $mode = (string) config('dantepos_mpesa.sale_mode', 'log');
        if (! in_array($mode, ['log', 'block'], true)) {
            return [];
        }

        $bid = (int) $transaction->business_id;
        $checkout = trim((string) ($payment['mpesa_checkout_request_id'] ?? ''));
        $receipt = strtoupper(preg_replace('/\s+/', '', (string) ($payment['mpesa_receipt_number'] ?? '')));
        $problem = null;
        $unverified = ['mpesa_unverified' => 1];

        if ($checkout !== '') {
            $mt = MpesaTransaction::where('checkout_request_id', $checkout)
                ->where('business_id', $bid)
                ->lockForUpdate()
                ->first();

            if (! $mt) {
                $problem = 'M-Pesa payment not found.';
            } elseif ($mt->status !== 'success') {
                $problem = 'M-Pesa payment is not confirmed (status: '.$mt->status.').';
            } elseif (! empty($mt->claimed_at) || ! empty($mt->transaction_id)) {
                $problem = 'This M-Pesa payment ('.$mt->mpesa_receipt_number.') has already been used.';
            } elseif ($amount > (float) $mt->amount + 0.99) {
                $problem = 'Amount entered (KSh '.number_format($amount, 2).') is more than the M-Pesa payment received (KSh '.number_format((float) $mt->amount, 2).').';
            }

            if ($problem === null) {
                $mt->forceFill(['claimed_at' => now(), 'transaction_id' => $transaction->id])->save();

                return [
                    'mpesa_unverified' => 0,
                    'mpesa_phone' => $mt->phone,
                    'mpesa_receipt_number' => $mt->mpesa_receipt_number,
                    'mpesa_checkout_request_id' => $mt->checkout_request_id,
                    'mpesa_merchant_request_id' => $mt->merchant_request_id,
                ];
            }
        } elseif (! preg_match('/^[A-Z0-9]{10}$/', $receipt)) {
            $problem = 'Send the STK Push, or enter the 10-character M-Pesa code from the customer\'s payment message.';
        } else {
            $usedOnSale = DB::table('transaction_payments')
                ->where('business_id', $bid)->where('mpesa_receipt_number', $receipt)->exists();
            $usedInLog = MpesaTransaction::where('business_id', $bid)
                ->where('mpesa_receipt_number', $receipt)->whereNotNull('claimed_at')->exists();

            if ($usedOnSale || $usedInLog) {
                $problem = 'M-Pesa code '.$receipt.' has already been used on another sale.';
            } else {
                $mt = MpesaTransaction::where('business_id', $bid)
                    ->where('mpesa_receipt_number', $receipt)
                    ->where('status', 'success')
                    ->whereNull('claimed_at')
                    ->lockForUpdate()
                    ->first();

                if ($mt && $amount <= (float) $mt->amount + 0.99) {
                    $mt->forceFill(['claimed_at' => now(), 'transaction_id' => $transaction->id])->save();

                    return [
                        'mpesa_unverified' => 0,
                        'mpesa_phone' => $mt->phone,
                        'mpesa_receipt_number' => $receipt,
                    ];
                }

                if (MpesaSettingsService::manualAllowed($bid, $amount)) {
                    return $unverified + ['mpesa_receipt_number' => $receipt];
                }

                $problem = 'This amount is above the limit for manual M-Pesa payments. Send an STK push instead.';
            }
        }

        Log::warning('M-Pesa sale payment not verified', [
            'mode' => $mode,
            'business_id' => $bid,
            'transaction_id' => $transaction->id ?? null,
            'amount' => $amount,
            'receipt' => $receipt ?: null,
            'problem' => $problem,
        ]);

        if ($mode === 'block') {
            throw new \App\Exceptions\PurchaseSellMismatch($problem);
        }

        return $unverified + ($receipt !== '' ? ['mpesa_receipt_number' => $receipt] : []);
    }
}
