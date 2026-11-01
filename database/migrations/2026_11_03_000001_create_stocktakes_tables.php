<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStocktakesTables extends Migration
{
    public function up()
    {
        Schema::create('stocktakes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('location_id');
            $table->string('ref_no', 30)->nullable();
            $table->string('name', 120)->nullable();
            $table->unsignedInteger('category_id')->nullable();
            $table->string('status', 20)->default('counting'); // counting, applied, cancelled
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('applied_by')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->unsignedInteger('stock_adjustment_id')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('stocktake_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stocktake_id')->index();
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('variation_id');
            $table->string('name', 255);
            $table->string('sku', 100)->nullable();
            $table->decimal('system_qty', 22, 4)->default(0);
            $table->decimal('unit_cost', 22, 4)->default(0);
            $table->decimal('counted_qty', 22, 4)->nullable();
            $table->decimal('variance', 22, 4)->nullable();
            $table->timestamps();
            $table->index(['stocktake_id', 'variation_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('stocktake_lines');
        Schema::dropIfExists('stocktakes');
    }
}
