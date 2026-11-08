<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGiftCardsTables extends Migration
{
    public function up()
    {
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('code', 24);
            $table->string('code_key', 24)->unique();
            $table->decimal('initial_amount', 22, 4);
            $table->decimal('balance', 22, 4);
            $table->string('status', 12)->default('active'); // active, void
            $table->date('expires_at')->nullable();
            $table->string('recipient_name', 100)->nullable();
            $table->string('recipient_phone', 20)->nullable();
            $table->string('issued_via', 20)->default('cash'); // cash, mpesa, card, bank_transfer, free
            $table->string('note', 255)->nullable();
            $table->unsignedInteger('issued_by')->nullable();
            $table->timestamps();
        });

        Schema::create('gift_card_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gift_card_id')->index();
            $table->unsignedInteger('business_id')->index();
            $table->string('type', 12); // issue, redeem, refund, adjust, void
            $table->decimal('amount', 22, 4); // signed: negative reduces the balance
            $table->decimal('balance_after', 22, 4);
            $table->unsignedInteger('transaction_payment_id')->nullable()->index();
            $table->unsignedInteger('transaction_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('gift_card_transactions');
        Schema::dropIfExists('gift_cards');
    }
}
