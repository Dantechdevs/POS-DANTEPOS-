<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('dantepos_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('bank_name', 100);
            $table->string('branch_name', 100)->nullable();
            $table->string('account_name', 120);
            $table->string('account_number', 40);
            $table->string('branch_code', 20)->nullable();
            $table->string('swift_code', 11)->nullable();
            $table->string('currency', 3)->default('KES');
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'bank_name', 'account_number'], 'dante_bank_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('dantepos_bank_accounts');
    }
};
