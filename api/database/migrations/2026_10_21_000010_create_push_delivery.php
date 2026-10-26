<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_devices', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained();
            $t->foreignUuid('authentication_session_id')->constrained();
            $t->char('token_hash', 64)->unique();
            $t->text('token_ciphertext');
            $t->string('platform', 16)->default('android');
            $t->timestampTz('revoked_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('push_deliveries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('school_notification_id')->constrained();
            $t->foreignUuid('push_device_id')->constrained();
            $t->string('status', 16)->default('pending');
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestampTz('next_attempt_at')->nullable();
            $t->timestampTz('lease_until')->nullable();
            $t->string('failure_code', 40)->nullable();
            $t->timestampsTz();
            $t->unique(['school_notification_id', 'push_device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_deliveries');
        Schema::dropIfExists('push_devices');
    }
};
