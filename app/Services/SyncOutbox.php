<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncOutbox
{
    /** Queue a finished POS sale for upload. Runs inside the sale's own DB transaction. */
    public static function captureSale($transaction, Request $request): void
    {
        if (config('sync.role') !== 'satellite') {
            return;
        }
        if ($transaction->type !== 'sell' || $transaction->status !== 'final' || ! empty($transaction->is_suspend)) {
            return;
        }

        $deviceId = (string) config('sync.device_id');
        if ($deviceId === '') {
            throw new \RuntimeException('This install is set to satellite but DANTE_DEVICE_ID is empty. Register the device on the server and set it in .env before selling.');
        }

        $req = $request->except(['_token', 'sell_document', 'shipping_documents', 'documents']);
        if (isset($req['payment']) && is_array($req['payment'])) {
            foreach (array_keys($req['payment']) as $i) {
                if (is_array($req['payment'][$i])) {
                    unset($req['payment'][$i]['card_security']); // never queue card security codes
                }
            }
        }

        $payload = json_encode([
            'meta' => [
                'device_id' => $deviceId,
                'transaction_id' => $transaction->id,
                'invoice_no' => $transaction->invoice_no,
                'transaction_date' => (string) $transaction->transaction_date,
                'business_id' => $transaction->business_id,
                'location_id' => $transaction->location_id,
                'contact_id' => $transaction->contact_id,
                'created_by' => $transaction->created_by,
                'final_total' => $transaction->final_total,
                'captured_at' => now()->toDateTimeString(),
            ],
            'request' => $req,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            throw new \RuntimeException('Could not queue the sale for sync: '.json_last_error_msg());
        }

        DB::table('sync_outbox')->insertOrIgnore([
            'uuid' => $deviceId.':'.$transaction->id,
            'entity' => 'sale',
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
