<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('dantepos_payment_channels', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('channel', 20); // airtel_money | equitel | pesalink | card
            $table->boolean('is_enabled')->default(false);
            $table->string('business_name', 80)->nullable();
            $table->string('account_number', 40)->nullable();
            $table->string('account_reference', 60)->nullable();
            $table->text('instructions')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'channel']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('dantepos_payment_channels');
    }
};
