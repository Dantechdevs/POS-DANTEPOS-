<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSmsReceiptsTable extends Migration
{
    public function up()
    {
        Schema::create('sms_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('transaction_id')->unique();
            $table->unsignedInteger('business_id')->nullable()->index();
            $table->string('phone', 20)->nullable();
            $table->string('status', 20)->default('sending'); // sending, sent, failed
            $table->string('error', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sms_receipts');
    }
}
