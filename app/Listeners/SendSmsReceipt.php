<?php

namespace App\Listeners;

use App\Services\SmsReceiptService;
use Illuminate\Support\Facades\Log;

class SendSmsReceipt
{
    public function handle($event): void
    {
        try {
            if (! config('services.sms_receipt.enabled')) {
                return;
            }

            $transaction = data_get($event, 'transaction');
            $id = (int) data_get($transaction, 'id');

            if (! $id) {
                return;
            }

            // Run after the response, once the sale transaction has been committed.
            app()->terminating(function () use ($id) {
                try {
                    app(SmsReceiptService::class)->send($id);
                } catch (\Throwable $e) {
                    Log::error('SMS receipt error: '.$e->getMessage());
                }
            });
        } catch (\Throwable $e) {
            // Never let an SMS problem break a sale.
            Log::error('SMS receipt listener error: '.$e->getMessage());
        }
    }
}
