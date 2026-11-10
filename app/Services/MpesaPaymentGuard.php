<?php

namespace App\Services;

use App\Exceptions\PurchaseSellMismatch;
use App\Models\MpesaTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    private const PHONE_REGEX = '/^(?:254|0)?([17]\d{8})$/';

    /** Form-only fields that are not columns on transaction_payments and must never reach update(). */
    private const FORM_ONLY_FIELDS = ['mpesa_manual', 'mpesa_manual_receipt', 'mpesa_reference', 'mpesa_transaction_date'];

    /** 10 characters, letters and digits, at least one letter (e.g. SGH7K2L9QX). */
    public static function normaliseReceipt(?string $code): ?string
    {
        $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code));

        return preg_match('/^(?=.*[A-Z])[A-Z0-9]{10}$/', $c) ? $c : null;
    }

    public static function normalisePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return preg_match(self::PHONE_REGEX, $digits, $m) ? '254'.$m[1] : null;
    }

    /**
     * Stage 1, before the sale is saved and with no writes: returns a message when the
     * sale's M-Pesa payment lines are not backed by a confirmed or manually entered payment.
     */
    public static function violation(Request $request, int $businessId, ?int $transactionId = null): ?string
    {
        if ($request->input('status') !== 'final' || ! empty($request->input('is_suspend'))) {
            return null;
        }

        try {
            self::resolve(
                (array) $request->input('payment', []),
                $businessId,
                (int) auth()->id(),
                null,
                $transactionId,
                false,
                fn ($v) => (float) app(\App\Utils\Util::class)->num_uf($v)
            );
        } catch (PurchaseSellMismatch $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * Stage 2, inside the sale's DB transaction: claims the proof for every M-Pesa line and returns the
     * payment lines with receipt data taken from the database, never from the browser.
     * Throws PurchaseSellMismatch (the sale rolls back) on any problem.
     */
    public static function claimForSale(array $payments, int $businessId, int $userId, ?int $locationId, ?int $transactionId, callable $parse): array
    {
        return self::resolve($payments, $businessId, $userId, $locationId, $transactionId, true, $parse);
    }

    /** After the payment lines exist: tie each used M-Pesa payment to its sale for the audit trail. */
    public static function linkToTransaction(array $payments, int $transactionId, int $businessId): void
    {
        foreach ($payments as $p) {
            if (! is_array($p) || ($p['method'] ?? null) !== 'mpesa' || empty($p['mpesa_receipt_number'])) {
                continue;
            }

            DB::table('mpesa_transactions')
                ->where('business_id', $businessId)
                ->where('mpesa_receipt_number', $p['mpesa_receipt_number'])
                ->whereNull('transaction_id')
                ->update(['transaction_id' => $transactionId]);
        }
    }

    private static function resolve(array $payments, int $businessId, int $userId, ?int $locationId, ?int $transactionId, bool $write, callable $parse): array
    {
        $settings = MpesaSettings::forBusiness($businessId);
        $out = $payments;
        $usedCheckoutIds = [];
        $usedReceipts = [];
        $manualThisSale = 0.0;

        foreach ($payments as $i => $p) {
            if (! is_array($p)) {
                continue;
            }

            foreach (self::FORM_ONLY_FIELDS as $f) {
                unset($out[$i][$f]);
            }

            if (($p['method'] ?? null) !== 'mpesa' || ! empty($p['is_return'])) {
                continue;
            }

            $amount = (float) $parse($p['amount'] ?? 0);

            if (! empty($p['payment_id'])) {
                $kept = self::keepExisting($p, $amount, $businessId, $transactionId);

                if ($kept !== null) {
                    $out[$i] = array_merge($out[$i], $kept);

                    continue;
                }
            }

            if ($amount <= 0) {
                continue;
            }

            if (! empty($p['mpesa_checkout_request_id'])) {
                $cid = (string) $p['mpesa_checkout_request_id'];

                if (isset($usedCheckoutIds[$cid])) {
                    throw new PurchaseSellMismatch('The same M-Pesa payment is used on two lines.');
                }
                $usedCheckoutIds[$cid] = true;

                $mt = self::confirmedStkRow($cid, $amount, $businessId, $write);

                if ($write) {
                    $mt->update(['claimed_at' => now()]);
                }

                $out[$i]['mpesa_receipt_number'] = $mt->mpesa_receipt_number;
                $out[$i]['mpesa_phone'] = $mt->phone;
                $out[$i]['mpesa_checkout_request_id'] = $mt->checkout_request_id;
                $out[$i]['mpesa_merchant_request_id'] = $mt->merchant_request_id;

                continue;
            }

            if (! empty($p['mpesa_manual'])) {
                $receipt = self::normaliseReceipt($p['mpesa_manual_receipt'] ?? '');
                $phone = self::normalisePhone($p['mpesa_phone'] ?? '');

                if (! $settings->manual_enabled) {
                    throw new PurchaseSellMismatch('Manual M-Pesa entry is switched off. Send an STK Push instead.');
                }
                if (! $receipt) {
                    throw new PurchaseSellMismatch('Enter the 10-character M-Pesa code from the customer\'s confirmation message, for example SGH7K2L9QX.');
                }
                if (! $phone) {
                    throw new PurchaseSellMismatch('Enter the customer\'s Safaricom number, for example 0712345678.');
                }
                if (isset($usedReceipts[$receipt])) {
                    throw new PurchaseSellMismatch('The M-Pesa code '.$receipt.' is used on two lines.');
                }
                $usedReceipts[$receipt] = true;

                if (self::receiptAlreadyRecorded($receipt, $businessId)) {
                    throw new PurchaseSellMismatch('The M-Pesa code '.$receipt.' has already been used on another sale.');
                }

                if ($settings->cap > 0) {
                    $open = (float) DB::table('mpesa_transactions')
                        ->where('business_id', $businessId)->where('source', 'manual')->where('status', 'manual')
                        ->where('createdBy', $userId)->sum('amount');

                    if ($open + $manualThisSale + $amount > $settings->cap + 0.001) {
                        throw new PurchaseSellMismatch('Limit for unverified manual M-Pesa payments reached (KSh '.number_format($open + $manualThisSale, 2)
                            .' of KSh '.number_format($settings->cap, 2).'). A manager must verify earlier manual payments first.');
                    }
                }
                $manualThisSale += $amount;

                if ($write) {
                    try {
                        MpesaTransaction::create([
                            'business_id' => $businessId,
                            'location_id' => $locationId,
                            'transaction_id' => $transactionId,
                            'phone' => $phone,
                            'amount' => $amount,
                            'status' => 'manual',
                            'source' => 'manual',
                            'mpesa_receipt_number' => $receipt,
                            'result_desc' => 'Entered by hand at the till',
                            'transaction_date' => now(),
                            'claimed_at' => now(),
                            'createdBy' => $userId,
                        ]);
                    } catch (\Illuminate\Database\QueryException $e) {
                        // The unique (business, receipt) index stopped a simultaneous duplicate.
                        throw new PurchaseSellMismatch('The M-Pesa code '.$receipt.' has already been used on another sale.');
                    }
                }

                $out[$i]['mpesa_receipt_number'] = $receipt;
                $out[$i]['mpesa_phone'] = $phone;
                $out[$i]['mpesa_checkout_request_id'] = null;
                $out[$i]['mpesa_merchant_request_id'] = null;

                continue;
            }

            throw new PurchaseSellMismatch('M-Pesa payment is not confirmed. Send the STK Push and wait for the customer to pay, or enter the M-Pesa code by hand.');
        }

        return $out;
    }

    private static function confirmedStkRow(string $checkoutRequestId, float $amount, int $businessId, bool $lock): MpesaTransaction
    {
        $q = MpesaTransaction::where('checkout_request_id', $checkoutRequestId)
            ->where(function ($w) use ($businessId) {
                $w->where('business_id', $businessId)->orWhereNull('business_id');
            });

        if ($lock) {
            $q->lockForUpdate();
        }

        $mt = $q->first();

        if (! $mt) {
            throw new PurchaseSellMismatch('M-Pesa payment not found. Send a new STK Push.');
        }
        if ($mt->status !== 'success') {
            throw new PurchaseSellMismatch('M-Pesa payment is not confirmed (status: '.$mt->status.').');
        }
        if (! empty($mt->claimed_at) || ! empty($mt->transaction_id)) {
            throw new PurchaseSellMismatch('This M-Pesa payment ('.$mt->mpesa_receipt_number.') has already been used.');
        }
        if ($amount > (float) $mt->amount + 0.99) {
            throw new PurchaseSellMismatch('Amount entered (KSh '.number_format($amount, 2).') is more than the M-Pesa payment received (KSh '.number_format((float) $mt->amount, 2).').');
        }

        return $mt;
    }

    /** An M-Pesa line that was already saved on this sale keeps its proven receipt data. Returns null if it is really a new proof. */
    private static function keepExisting(array $p, float $amount, int $businessId, ?int $transactionId): ?array
    {
        $tp = DB::table('transaction_payments')
            ->where('id', (int) $p['payment_id'])->where('business_id', $businessId)->first();

        if (! $tp || ($transactionId && (int) $tp->transaction_id !== $transactionId)) {
            throw new PurchaseSellMismatch('A payment line does not belong to this sale.');
        }

        if ($tp->method !== 'mpesa') {
            return null; // switched to M-Pesa now: needs fresh proof
        }

        if ($amount > (float) $tp->amount + 0.99) {
            throw new PurchaseSellMismatch('An M-Pesa amount cannot be raised on an existing line. Add a new M-Pesa payment for the extra money.');
        }

        return [
            'mpesa_phone' => $tp->mpesa_phone,
            'mpesa_receipt_number' => $tp->mpesa_receipt_number,
            'mpesa_checkout_request_id' => $tp->mpesa_checkout_request_id,
            'mpesa_merchant_request_id' => $tp->mpesa_merchant_request_id,
        ];
    }

    private static function receiptAlreadyRecorded(string $receipt, int $businessId): bool
    {
        return DB::table('mpesa_transactions')->where('business_id', $businessId)->where('mpesa_receipt_number', $receipt)->exists()
            || DB::table('transaction_payments')->where('business_id', $businessId)->where('mpesa_receipt_number', $receipt)->exists();
    }
}
