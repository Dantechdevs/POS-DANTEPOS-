<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('etims_invoices', function (Blueprint $table) {
            $table->id(); // also used as the sequential eTIMS invoice number
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('transaction_id')->unique();
            $table->string('status', 12)->default('pending')->index(); // pending | submitted | failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->longText('payload')->nullable();
            $table->longText('response')->nullable();
            $table->string('cu_receipt_no', 60)->nullable();
            $table->text('cu_signature')->nullable();
            $table->string('cu_datetime', 30)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('etims_invoices');
    }
};
