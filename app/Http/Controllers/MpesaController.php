<?php

namespace App\Http\Controllers;

use App\Models\MpesaTransaction;
use App\Services\MpesaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MpesaController extends Controller
{
    /** Accepts 07XXXXXXXX, 01XXXXXXXX, 7XXXXXXXX, 2547XXXXXXXX and +2547XXXXXXXX. */
    private const PHONE_REGEX = '/^(?:254|0)?([17]\d{8})$/';

    /** Daraja STK Push per-transaction ceiling (KES). */
    private const MAX_AMOUNT = 250000;

    /** Ask Safaricom directly if the callback has not arrived after this many seconds. */
    private const QUERY_AFTER_SECONDS = 20;

    protected $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    /**
     * Send an STK Push to the customer's phone.
     */
    public function initiate(Request $request)
    {
        if (!auth()->user()->can('sell.create')
            && !auth()->user()->can('direct_sell.access')
            && !auth()->user()->can('edit_pos_payment')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'phone' => 'required|string|max:20',
            'amount' => 'required|numeric|min:1|max:' . self::MAX_AMOUNT,
            'location_id' => 'nullable|integer',
        ]);

        $phone = $this->normalisePhone($request->phone);
        if (!$phone) {
            return response()->json([
                'success' => false,
                'message' => 'Enter a valid Safaricom number, for example 0712345678.',
            ], 422);
        }

        // Daraja only supports whole shillings.
        $amount = (float) $request->amount;
        if (floor($amount) != $amount) {
            return response()->json([
                'success' => false,
                'message' => 'M-Pesa accepts whole shillings only. Collect the cents in cash.',
            ], 422);
        }
        $amount = (int) $amount;

        $business_id = $request->session()->get('user.business_id');

        try {
            // Guard against double-taps: reuse a very recent identical pending push.
            $recent = MpesaTransaction::where('business_id', $business_id)
                ->where('phone', $phone)
                ->where('amount', $amount)
                ->where('status', 'pending')
                ->where('created_at', '>=', now()->subSeconds(60))
                ->latest('id')
                ->first();

            if ($recent) {
                return response()->json([
                    'success' => true,
                    'message' => 'A prompt was already sent to this number. Ask the customer to enter their M-Pesa PIN.',
                    'checkout_request_id' => $recent->checkout_request_id,
                ]);
            }

            // AccountReference is limited to 12 characters by Daraja.
            $reference = 'POS' . strtoupper(Str::random(8));

            $result = $this->mpesaService->stkPush($phone, $amount, $reference, 'POS Sale');

            if (!isset($result['CheckoutRequestID'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['errorMessage'] ?? 'Failed to initiate M-Pesa payment.',
                ], 422);
            }

            $transaction = MpesaTransaction::create([
                'business_id' => $business_id,
                'location_id' => $request->location_id,
                'phone' => $phone,
                'amount' => $amount,
                'account_reference' => $reference,
                'merchant_request_id' => $result['MerchantRequestID'] ?? null,
                'checkout_request_id' => $result['CheckoutRequestID'],
                'status' => 'pending',
                'createdBy' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'STK push sent. Ask the customer to enter their M-Pesa PIN.',
                'checkout_request_id' => $transaction->checkout_request_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('M-Pesa initiate error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Could not reach M-Pesa. Please try again.',
            ], 500);
        }
    }

    /**
     * Polled by the POS while waiting for the customer to pay.
     */
    public function status(Request $request, $checkoutRequestId)
    {
        $business_id = $request->session()->get('user.business_id');

        $transaction = MpesaTransaction::where('checkout_request_id', $checkoutRequestId)
            ->where('business_id', $business_id)
            ->first();

        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found.'], 404);
        }

        // Fallback for lost callbacks: ask Safaricom directly.
        if ($transaction->status === 'pending'
            && $transaction->created_at
            && $transaction->created_at->diffInSeconds(now()) >= self::QUERY_AFTER_SECONDS
            && method_exists($this->mpesaService, 'stkQuery')) {
            try {
                $query = $this->mpesaService->stkQuery($transaction->checkout_request_id);

                // While Safaricom is still processing, the response has no ResultCode.
                if (isset($query['ResultCode'])) {
                    $this->applyResult(
                        $transaction->checkout_request_id,
                        (int) $query['ResultCode'],
                        $query['ResultDesc'] ?? null,
                        collect()
                    );
                    $transaction->refresh();
                }
            } catch (\Throwable $e) {
                Log::warning('M-Pesa STK query failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'status' => $transaction->status,
            'mpesa_receipt_number' => $transaction->mpesa_receipt_number,
            'result_desc' => $transaction->result_desc,
        ]);
    }

    /**
     * Safaricom callback. The URL carries a secret segment because Daraja does not sign callbacks.
     */
    public function callback(Request $request, $secret = null)
    {
        $accepted = response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);

        $expected = (string) config('mpesa.callback_secret');
        if ($expected === '' || !hash_equals($expected, (string) $secret)) {
            Log::warning('M-Pesa callback rejected: invalid secret.');

            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Rejected'], 403);
        }

        $callback = $request->input('Body.stkCallback');

        if (!is_array($callback) || empty($callback['CheckoutRequestID'])) {
            return $accepted;
        }

        $items = collect($callback['CallbackMetadata']['Item'] ?? [])
            ->filter(fn ($item) => is_array($item) && isset($item['Name']))
            ->mapWithKeys(fn ($item) => [$item['Name'] => $item['Value'] ?? null]);

        try {
            $this->applyResult(
                (string) $callback['CheckoutRequestID'],
                (int) ($callback['ResultCode'] ?? -1),
                $callback['ResultDesc'] ?? null,
                $items,
                $request->getContent()
            );
        } catch (\Throwable $e) {
            // The STK query fallback in status() will recover this payment.
            Log::error('M-Pesa callback processing error: ' . $e->getMessage());
        }

        return $accepted;
    }

    /**
     * Apply a payment result exactly once. Safe against duplicate and late callbacks.
     */
    private function applyResult(string $checkoutRequestId, int $resultCode, ?string $resultDesc, Collection $items, ?string $rawPayload = null): void
    {
        DB::transaction(function () use ($checkoutRequestId, $resultCode, $resultDesc, $items, $rawPayload) {
            $transaction = MpesaTransaction::where('checkout_request_id', $checkoutRequestId)
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                Log::warning('M-Pesa result for unknown CheckoutRequestID ' . $checkoutRequestId);

                return;
            }

            $receipt = $items->get('MpesaReceiptNumber');

            // Already successful: never downgrade, only fill in a missing receipt number.
            if ($transaction->status === 'success') {
                if (empty($transaction->mpesa_receipt_number) && $receipt) {
                    $transaction->update(['mpesa_receipt_number' => $receipt]);
                }

                return;
            }

            // Only a pending transaction may change state.
            if ($transaction->status !== 'pending') {
                return;
            }

            if ($resultCode === 0) {
                $paid = $items->get('Amount');

                // The amount paid must cover the amount requested.
                if ($paid !== null && (float) $paid + 0.001 < (float) $transaction->amount) {
                    Log::warning('M-Pesa amount mismatch for ' . $checkoutRequestId);
                    $transaction->update([
                        'status' => 'review',
                        'result_code' => $resultCode,
                        'result_desc' => 'Amount mismatch: requires manual review',
                        'raw_callback' => $rawPayload,
                    ]);

                    return;
                }

                $transaction->update([
                    'status' => 'success',
                    'mpesa_receipt_number' => $receipt,
                    'result_code' => $resultCode,
                    'result_desc' => $resultDesc,
                    'transaction_date' => $this->parseDate($items->get('TransactionDate')),
                    'raw_callback' => $rawPayload,
                ]);

                return;
            }

            $transaction->update([
                'status' => $resultCode === 1032 ? 'cancelled' : 'failed',
                'result_code' => $resultCode,
                'result_desc' => $resultDesc,
                'raw_callback' => $rawPayload,
            ]);
        });
    }

    private function parseDate($value): Carbon
    {
        if (empty($value)) {
            return now();
        }

        try {
            return Carbon::createFromFormat('YmdHis', (string) $value, 'Africa/Nairobi');
        } catch (\Throwable $e) {
            return now();
        }
    }

    private function normalisePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        return preg_match(self::PHONE_REGEX, $digits, $m) ? '254' . $m[1] : null;
    }
}
