<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('dantepos_payment_config', function (Blueprint $table) {
            $table->unsignedInteger('business_id')->primary();
            $table->longText('config');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('dantepos_payment_config');
    }
};
