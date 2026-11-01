<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBusinessAndAuditColumnsToMpesaTransactionsTable extends Migration
{
    public function up()
    {
        Schema::table('mpesa_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('mpesa_transactions', 'business_id')) {
                $table->unsignedInteger('business_id')->nullable()->index();
            }
            if (!Schema::hasColumn('mpesa_transactions', 'location_id')) {
                $table->unsignedInteger('location_id')->nullable();
            }
            // The POS sale that consumed this payment (set when the sale is saved).
            if (!Schema::hasColumn('mpesa_transactions', 'transaction_id')) {
                $table->unsignedInteger('transaction_id')->nullable()->index();
            }
            if (!Schema::hasColumn('mpesa_transactions', 'claimed_at')) {
                $table->timestamp('claimed_at')->nullable();
            }
            if (!Schema::hasColumn('mpesa_transactions', 'raw_callback')) {
                $table->longText('raw_callback')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->dropColumn(['business_id', 'location_id', 'transaction_id', 'claimed_at', 'raw_callback']);
        });
    }
}
