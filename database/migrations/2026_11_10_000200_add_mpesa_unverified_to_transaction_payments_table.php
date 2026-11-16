<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transaction_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('transaction_payments', 'mpesa_unverified')) {
                $table->boolean('mpesa_unverified')->default(false)->after('mpesa_merchant_request_id');
            }
        });
    }

    public function down()
    {
        Schema::table('transaction_payments', function (Blueprint $table) {
            if (Schema::hasColumn('transaction_payments', 'mpesa_unverified')) {
                $table->dropColumn('mpesa_unverified');
            }
        });
    }
};
