<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // On the primary: the satellites allowed to connect.
        Schema::create('sync_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 30)->unique();
            $table->string('name', 80);
            $table->string('token_hash', 64)->index();
            $table->boolean('active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        // On a satellite: records waiting to be uploaded.
        Schema::create('sync_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 40)->unique();
            $table->string('entity', 30);
            $table->longText('payload');
            $table->string('status', 10)->default('pending')->index(); // pending | sent | failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        // On the primary: records received from satellites.
        Schema::create('sync_inbox', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 40)->unique();
            $table->string('device_id', 30)->index();
            $table->string('entity', 30);
            $table->longText('payload');
            $table->string('status', 10)->default('received')->index(); // received | applied | failed
            $table->text('error')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sync_inbox');
        Schema::dropIfExists('sync_outbox');
        Schema::dropIfExists('sync_devices');
    }
};
