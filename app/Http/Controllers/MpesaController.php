<?php

namespace App\Http\Controllers;

use App\Models\MpesaTransaction;
use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MpesaController extends Controller
{
    protected $mpesaService;

    public function __construct(MpesaService $mpesaService)
    {
        $this->mpesaService = $mpesaService;
    }

    public function initiate(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:9',
            'amount' => 'required|numeric|min:1',
        ]);

        try {
            $reference = 'POS' . time();

            $result = $this->mpesaService->stkPush(
                $request->phone,
                $request->amount,
                $reference,
                'POS Sale Payment'
            );

            if (!isset($result['CheckoutRequestID'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['errorMessage'] ?? 'Failed to initiate M-Pesa payment.',
                ], 422);
            }

            $transaction = MpesaTransaction::create([
                'phone' => $request->phone,
                'amount' => $request->amount,
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
        } catch (\Exception $e) {
            Log::error('M-Pesa initiate error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Could not reach M-Pesa. Please try again.',
            ], 500);
        }
    }

    public function status($checkoutRequestId)
    {
        $transaction = MpesaTransaction::where('checkout_request_id', $checkoutRequestId)->first();

        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'status' => $transaction->status,
            'mpesa_receipt_number' => $transaction->mpesa_receipt_number,
            'result_desc' => $transaction->result_desc,
        ]);
    }

    public function callback(Request $request)
    {
        Log::info('M-Pesa callback received: ' . $request->getContent());

        $callback = $request->input('Body.stkCallback');

        if (!$callback) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $transaction = MpesaTransaction::where('checkout_request_id', $callback['CheckoutRequestID'] ?? null)->first();

        if (!$transaction) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $resultCode = $callback['ResultCode'] ?? null;

        if ($resultCode === 0) {
            $metadata = collect($callback['CallbackMetadata']['Item'] ?? []);

            $receipt = $metadata->firstWhere('Name', 'MpesaReceiptNumber')['Value'] ?? null;
            $transactionDate = $metadata->firstWhere('Name', 'TransactionDate')['Value'] ?? null;

            $transaction->update([
                'status' => 'success',
                'mpesa_receipt_number' => $receipt,
                'result_code' => $resultCode,
                'result_desc' => $callback['ResultDesc'] ?? null,
                'transaction_date' => $transactionDate ? \Carbon\Carbon::createFromFormat('YmdHis', $transactionDate) : now(),
            ]);
        } else {
            $transaction->update([
                'status' => $resultCode == 1032 ? 'cancelled' : 'failed',
                'result_code' => $resultCode,
                'result_desc' => $callback['ResultDesc'] ?? null,
            ]);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
