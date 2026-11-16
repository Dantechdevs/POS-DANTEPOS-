<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('business', function (Blueprint $table) {
            if (! Schema::hasColumn('business', 'rp_points_per_1000')) {
                $table->decimal('rp_points_per_1000', 8, 2)->nullable()->comment('rp = reward points');
            }
            if (! Schema::hasColumn('business', 'rp_season_only')) {
                $table->boolean('rp_season_only')->default(0);
            }
            if (! Schema::hasColumn('business', 'rp_season_start')) {
                $table->date('rp_season_start')->nullable();
            }
            if (! Schema::hasColumn('business', 'rp_season_end')) {
                $table->date('rp_season_end')->nullable();
            }
            if (! Schema::hasColumn('business', 'rp_expiry_date')) {
                $table->date('rp_expiry_date')->nullable();
            }
            if (! Schema::hasColumn('business', 'rp_expired_on')) {
                $table->date('rp_expired_on')->nullable()->comment('last expiry date already processed');
            }
        });
    }

    public function down()
    {
        //
    }
};