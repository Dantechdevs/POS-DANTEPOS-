<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DanteBankAccount extends Model
{
    protected $table = 'dantepos_bank_accounts';

    protected $fillable = [
        'business_id', 'bank_name', 'branch_name', 'account_name', 'account_number',
        'branch_code', 'swift_code', 'currency', 'instructions', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];
}
