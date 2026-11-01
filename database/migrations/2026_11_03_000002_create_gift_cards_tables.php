<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('code', 20)->unique();
            $table->decimal('initial_amount', 22, 4);
            $table->decimal('balance', 22, 4);
            $table->string('status', 10)->default('active'); // active | void
            $table->string('customer_name', 120)->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->date('expires_at')->nullable();
            $table->unsignedInteger('issued_by')->nullable();
            $table->timestamps();
        });

        Schema::create('gift_card_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gift_card_id')->index();
            $table->unsignedInteger('business_id')->index();
            $table->string('type', 12); // issue | topup | redeem | restore | void
            $table->decimal('amount', 22, 4); // signed: + adds balance, - removes
            $table->decimal('balance_after', 22, 4);
            $table->unsignedInteger('transaction_id')->nullable();
            $table->unsignedInteger('transaction_payment_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('transaction_payments', function (Blueprint $table) {
            $table->string('gift_card_code', 20)->nullable();
        });
    }

    public function down()
    {
        Schema::table('transaction_payments', function (Blueprint $table) {
            $table->dropColumn('gift_card_code');
        });
        Schema::dropIfExists('gift_card_transactions');
        Schema::dropIfExists('gift_cards');
    }
};
