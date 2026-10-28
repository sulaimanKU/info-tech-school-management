<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('created_by')->constrained('users');
            $t->string('kind', 32);
            $t->string('fingerprint', 64);
            $t->text('encrypted_rows')->nullable();
            $t->json('mapping')->nullable();
            $t->string('status', 24)->default('uploaded');
            $t->json('report')->nullable();
            $t->timestampTz('expires_at');
            $t->timestampsTz();

            $t->unique(
                ['created_by', 'kind', 'fingerprint'],
                'import_batch_creator_kind_fingerprint_uq'
            );
        });

        Schema::create('biometric_mappings', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('device_code', 80);
            $t->string('external_id', 80);
            $t->foreignUuid('student_id')->constrained();
            $t->foreignUuid('created_by')->constrained('users');
            $t->timestampsTz();

            $t->unique(
                ['device_code', 'external_id'],
                'biometric_mapping_device_external_uq'
            );
        });

        Schema::create('biometric_punches', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('import_batch_id')->constrained();
            $t->foreignUuid('student_id')->constrained();
            $t->string('device_code', 80);
            $t->timestampTz('punched_at');
            $t->string('direction', 8);
            $t->timestampsTz();

            $t->unique(
                ['student_id', 'device_code', 'punched_at', 'direction'],
                'biometric_punch_student_device_time_direction_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_punches');
        Schema::dropIfExists('biometric_mappings');
        Schema::dropIfExists('import_batches');
    }
};