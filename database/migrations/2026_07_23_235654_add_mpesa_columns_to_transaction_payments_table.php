<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMpesaColumnsToTransactionPaymentsTable extends Migration
{
    public function up()
    {
        Schema::table('transaction_payments', function (Blueprint $table) {
            $table->string('mpesa_phone')->nullable()->after('bank_account_number');
            $table->string('mpesa_receipt_number')->nullable()->after('mpesa_phone');
            $table->string('mpesa_checkout_request_id')->nullable()->after('mpesa_receipt_number');
            $table->string('mpesa_merchant_request_id')->nullable()->after('mpesa_checkout_request_id');
        });
    }

    public function down()
    {
        Schema::table('transaction_payments', function (Blueprint $table) {
            $table->dropColumn(['mpesa_phone', 'mpesa_receipt_number', 'mpesa_checkout_request_id', 'mpesa_merchant_request_id']);
        });
    }
}
