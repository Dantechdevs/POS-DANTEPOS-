<?php

namespace App\Observers;

use App\Transaction;
use Illuminate\Support\Facades\DB;

class EtimsSaleObserver
{
    public function saved(Transaction $t)
    {
        if (! config('etims.enabled')) {
            return;
        }
        if ($t->type !== 'sell' || $t->status !== 'final') {
            return;
        }
        // Payload is built at submit time, once the sale lines exist.
        DB::table('etims_invoices')->insertOrIgnore([
            'business_id' => $t->business_id,
            'transaction_id' => $t->id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
