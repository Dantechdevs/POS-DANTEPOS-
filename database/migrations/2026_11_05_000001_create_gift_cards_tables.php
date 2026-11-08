<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gift_cards')) {
            Schema::create('gift_cards', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id');
                $t->string('code', 24);
                $t->string('code_key', 24)->index();
                $t->decimal('initial_amount', 22, 4);
                $t->decimal('balance', 22, 4);
                $t->string('status', 12)->default('active');
                $t->date('expires_at')->nullable();
                $t->string('recipient_name', 100)->nullable();
                $t->string('recipient_phone', 20)->nullable();
                $t->string('issued_via', 20)->default('cash');
                $t->string('note', 255)->nullable();
                $t->unsignedInteger('issued_by')->nullable();
                $t->timestamps();
            });
        } else {
            Schema::table('gift_cards', function (Blueprint $t) {
                if (! Schema::hasColumn('gift_cards', 'code_key')) {
                    $t->string('code_key', 24)->default('')->after('code');
                }
                if (! Schema::hasColumn('gift_cards', 'recipient_name')) {
                    $t->string('recipient_name', 100)->nullable();
                }
                if (! Schema::hasColumn('gift_cards', 'recipient_phone')) {
                    $t->string('recipient_phone', 20)->nullable();
                }
                if (! Schema::hasColumn('gift_cards', 'issued_via')) {
                    $t->string('issued_via', 20)->default('cash');
                }
                if (! Schema::hasColumn('gift_cards', 'note')) {
                    $t->string('note', 255)->nullable();
                }
            });

            // Carry old data across and fill code_key for any existing cards.
            if (Schema::hasColumn('gift_cards', 'customer_name')) {
                DB::table('gift_cards')->whereNull('recipient_name')->update(['recipient_name' => DB::raw('customer_name')]);
            }
            if (Schema::hasColumn('gift_cards', 'customer_phone')) {
                DB::table('gift_cards')->whereNull('recipient_phone')->update(['recipient_phone' => DB::raw('customer_phone')]);
            }
            foreach (DB::table('gift_cards')->where('code_key', '')->get(['id', 'code']) as $c) {
                DB::table('gift_cards')->where('id', $c->id)
                    ->update(['code_key' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $c->code))]);
            }

            try {
                Schema::table('gift_cards', fn (Blueprint $t) => $t->index('code_key'));
            } catch (\Throwable $e) {
                // index already exists
            }
        }

        if (! Schema::hasTable('gift_card_transactions')) {
            Schema::create('gift_card_transactions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('gift_card_id')->index();
                $t->unsignedInteger('business_id');
                $t->string('type', 12);
                $t->decimal('amount', 22, 4);
                $t->decimal('balance_after', 22, 4);
                $t->unsignedInteger('transaction_id')->nullable();
                $t->unsignedInteger('transaction_payment_id')->nullable()->index();
                $t->unsignedInteger('user_id')->nullable();
                $t->string('note', 255)->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Left empty on purpose: this migration may have adapted an existing table.
    }
};
