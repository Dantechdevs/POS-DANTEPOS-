<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOfflineSalesTable extends Migration
{
    public function up()
    {
        Schema::create('offline_sales', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid', 64)->unique();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('status', 20)->default('processing'); // processing, done, failed
            $table->unsignedInteger('transaction_id')->nullable();
            $table->string('invoice_no', 50)->nullable();
            $table->text('error')->nullable();
            $table->longText('payload')->nullable();
            $table->timestamp('client_time')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('offline_sales');
    }
}
