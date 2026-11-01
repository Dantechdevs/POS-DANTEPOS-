<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMpesaTransactionsTable extends Migration
{
    public function up()
    {
        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('sale_id')->nullable();
            $table->string('phone', 15);
            $table->decimal('amount', 15, 2);
            $table->string('account_reference')->nullable();
            $table->string('merchant_request_id')->nullable();
            $table->string('checkout_request_id')->nullable()->index();
            $table->string('status', 20)->default('pending');
            $table->string('mpesa_receipt_number')->nullable();
            $table->string('result_code')->nullable();
            $table->string('result_desc')->nullable();
            $table->timestamp('transaction_date')->nullable();
            $table->unsignedInteger('createdBy')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('mpesa_transactions');
    }
}
