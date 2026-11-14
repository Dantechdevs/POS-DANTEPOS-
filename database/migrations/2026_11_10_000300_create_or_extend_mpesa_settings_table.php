<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('mpesa_settings')) {
            Schema::create('mpesa_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->unique();
                $table->string('account_type', 10)->default('till');
                $table->string('shortcode', 10)->nullable();
                $table->string('till_number', 10)->nullable();
                $table->string('account_hint', 60)->nullable();
                $table->boolean('manual_enabled')->default(false);
                $table->decimal('manual_unverified_cap', 12, 2)->default(0);
                $table->timestamps();
            });
        }

        Schema::table('mpesa_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('mpesa_settings', 'daraja_env')) {
                $table->string('daraja_env', 12)->default('sandbox');
            }
            if (! Schema::hasColumn('mpesa_settings', 'consumer_key_enc')) {
                $table->text('consumer_key_enc')->nullable();
            }
            if (! Schema::hasColumn('mpesa_settings', 'consumer_secret_enc')) {
                $table->text('consumer_secret_enc')->nullable();
            }
            if (! Schema::hasColumn('mpesa_settings', 'passkey_enc')) {
                $table->text('passkey_enc')->nullable();
            }
            if (! Schema::hasColumn('mpesa_settings', 'stk_shortcode')) {
                $table->string('stk_shortcode', 10)->nullable();
            }
            if (! Schema::hasColumn('mpesa_settings', 'transaction_type')) {
                $table->string('transaction_type', 40)->default('CustomerBuyGoodsOnline');
            }
            if (! Schema::hasColumn('mpesa_settings', 'callback_secret')) {
                $table->string('callback_secret', 64)->nullable()->unique();
            }
        });
    }

    public function down()
    {
        Schema::table('mpesa_settings', function (Blueprint $table) {
            foreach (['daraja_env', 'consumer_key_enc', 'consumer_secret_enc', 'passkey_enc', 'stk_shortcode', 'transaction_type', 'callback_secret'] as $c) {
                if (Schema::hasColumn('mpesa_settings', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
