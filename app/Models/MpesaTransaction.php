<?php

namespace App\Models;

use App\Transaction;
use Illuminate\Database\Eloquent\Model;

class MpesaTransaction extends Model
{
    protected $fillable = [
        'sale_id', 'business_id', 'location_id', 'transaction_id', 'claimed_at',
        'phone', 'amount', 'account_reference', 'merchant_request_id',
        'checkout_request_id', 'status', 'mpesa_receipt_number', 'result_code',
        'result_desc', 'transaction_date', 'raw_callback', 'createdBy',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'claimed_at' => 'datetime',
    ];

    /** The POS sale this M-Pesa payment was used on. */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
