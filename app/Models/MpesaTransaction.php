<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpesaTransaction extends Model
{
    protected $fillable = [
        'sale_id', 'phone', 'amount', 'account_reference', 'merchant_request_id',
        'checkout_request_id', 'status', 'mpesa_receipt_number', 'result_code',
        'result_desc', 'transaction_date', 'createdBy',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}
