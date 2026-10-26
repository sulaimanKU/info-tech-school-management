<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['roles', 'permissions'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->string('code')->unique();
                $t->string('name');
                $t->timestampsTz();
            });
        }
        Schema::create('user_role_assignments', function (Blueprint $t) {
            $t->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('role_id')->constrained()->restrictOnDelete();
            $t->timestampsTz();
            $t->primary(['user_id', 'role_id']);
            $t->index('role_id');
        });
        Schema::create('permission_role', function (Blueprint $t) {
            $t->foreignUuid('permission_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('role_id')->constrained()->cascadeOnDelete();
            $t->primary(['permission_id', 'role_id']);
            $t->index('role_id');
        });
        Schema::create('school_settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->json('value');
            $t->timestampsTz();
        });
        Schema::create('academic_sessions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name')->unique();
            $t->date('starts_on');
            $t->date('ends_on');
            $t->timestampsTz();
        });
        Schema::create('classes', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name')->unique();
            $t->unsignedSmallInteger('sort_order')->unique();
            $t->timestampsTz();
        });
        Schema::create('sections', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('class_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('academic_session_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->timestampsTz();
            $t->unique(['class_id', 'academic_session_id', 'name']);
            $t->index('academic_session_id');
        });
        Schema::create('subjects', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code')->unique();
            $t->string('name');
            $t->timestampsTz();
        });
        Schema::create('authentication_sessions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $t->char('token_hash', 64)->unique();
            $t->string('method', 32)->default('staff_password');
            $t->timestampTz('expires_at')->index();
            $t->timestampTz('revoked_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('event', 64)->index();
            $t->timestampTz('created_at')->useCurrent()->index();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE academic_sessions ADD CONSTRAINT academic_dates CHECK (ends_on >= starts_on)');
            DB::statement('ALTER TABLE classes ADD CONSTRAINT class_order CHECK (sort_order >= 0)');
            DB::statement('ALTER TABLE authentication_sessions ADD CONSTRAINT session_expiry CHECK (expires_at > created_at)');
        }
    }

    public function down(): void
    {
        foreach (['audit_logs', 'authentication_sessions', 'subjects', 'sections', 'classes', 'academic_sessions', 'school_settings', 'permission_role', 'user_role_assignments', 'permissions', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
