<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Paybill/Till settings per business, plus the columns needed for manual M-Pesa payments
 * (cashier types the receipt code when STK Push is not possible) and their later verification.
 * Safe on fresh installs and on databases that already have the earlier M-Pesa tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mpesa_settings')) {
            Schema::create('mpesa_settings', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('business_id')->unique();
                $t->string('account_type', 10)->default('till');      // till | paybill
                $t->string('shortcode', 20)->nullable();              // STK BusinessShortCode: the paybill, or a till's store/head-office number
                $t->string('till_number', 20)->nullable();            // STK PartyB when account_type is till
                $t->string('account_hint', 80)->nullable();           // paybill: what the customer types as account number
                $t->boolean('manual_enabled')->default(true);
                $t->decimal('manual_unverified_cap', 15, 2)->default(20000); // 0 = no cap
                $t->timestamps();
            });

        }

        Schema::table('mpesa_transactions', function (Blueprint $t) {
            if (! Schema::hasColumn('mpesa_transactions', 'source')) {
                $t->string('source', 12)->default('stk');             // stk | manual
            }
            if (! Schema::hasColumn('mpesa_transactions', 'verified_at')) {
                $t->timestamp('verified_at')->nullable();
            }
            if (! Schema::hasColumn('mpesa_transactions', 'verified_by')) {
                $t->unsignedInteger('verified_by')->nullable();
            }
            if (! Schema::hasColumn('mpesa_transactions', 'verify_note')) {
                $t->string('verify_note', 255)->nullable();
            }
        });

        // One receipt code can only ever be recorded once per business.
        $dupes = DB::table('mpesa_transactions')
            ->whereNotNull('mpesa_receipt_number')->where('mpesa_receipt_number', '!=', '')
            ->groupBy('business_id', 'mpesa_receipt_number')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($dupes > 0) {
            Log::warning("mpesa_transactions has {$dupes} duplicated receipt numbers; unique index not added. Clean them up and re-run.");

            return;
        }

        try {
            Schema::table('mpesa_transactions', function (Blueprint $t) {

                $t->unique(['business_id', 'mpesa_receipt_number'], 'mpesa_receipt_unique');
            });
        } catch (\Throwable $e) {
            // index already exists
        }
    }

    public function down(): void
    {
        // Left empty on purpose: dropping would lose payment evidence.
    }
};
