<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_days', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('academic_session_id')->constrained()->restrictOnDelete();
            $t->date('date');
            $t->boolean('is_school_day');
            $t->string('label')->nullable();
            $t->timestampsTz();
            $t->unique(['academic_session_id', 'date']);
        });
        Schema::create('attendance_registers', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('section_id')->constrained()->restrictOnDelete();
            $t->date('date');
            $t->string('status', 20);
            $t->unsignedInteger('version')->default(1);
            $t->uuid('last_request_id');
            $t->char('payload_hash', 64);
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('updated_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['section_id', 'date']);
        });
        Schema::create('attendance_records', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('attendance_register_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('student_id')->constrained()->restrictOnDelete();
            $t->date('date');
            $t->string('status', 24);
            $t->timestampsTz();
            $t->unique(['student_id', 'date']);
        });
        Schema::create('attendance_revisions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('attendance_register_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['attendance_register_id', 'version']);
        });
        Schema::create('diary_entries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('section_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('subject_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('teacher_user_id')->constrained('users')->restrictOnDelete();
            $t->date('date');
            $t->string('topic', 300);
            $t->text('homework');
            $t->boolean('whole_class')->default(true);
            $t->string('status', 20)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->uuid('client_id')->unique();
            $t->char('payload_hash', 64);
            $t->timestampTz('reviewed_at')->nullable();
            $t->foreignUuid('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->index(['section_id', 'date', 'status']);
        });
        Schema::create('diary_students', function (Blueprint $t) {
            $t->foreignUuid('diary_entry_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('student_id')->constrained()->restrictOnDelete();
            $t->primary(['diary_entry_id', 'student_id']);
        });
        Schema::create('diary_attachments', function (Blueprint $t) {
            $t->foreignUuid('diary_entry_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('private_file_id')->constrained()->restrictOnDelete();
            $t->primary(['diary_entry_id', 'private_file_id']);
        });
        Schema::create('diary_revisions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('diary_entry_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['diary_entry_id', 'version']);
        });
        Schema::create('grading_rules', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('academic_session_id')->constrained()->restrictOnDelete();
            $t->decimal('minimum_percent', 5, 2);
            $t->string('grade', 20);
            $t->timestampsTz();
            $t->unique(['academic_session_id', 'minimum_percent']);
        });
        Schema::create('assessments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('section_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('subject_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('academic_session_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('teacher_user_id')->constrained('users')->restrictOnDelete();
            $t->string('type', 30);
            $t->string('title', 200);
            $t->date('date');
            $t->decimal('maximum_marks', 8, 2);
            $t->string('status', 20)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->foreignUuid('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('published_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('assessment_results', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('assessment_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('student_id')->constrained()->restrictOnDelete();
            $t->decimal('maximum_marks', 8, 2);
            $t->decimal('obtained_marks', 8, 2);
            $t->decimal('percentage', 5, 2);
            $t->string('grade', 20);
            $t->text('remarks')->nullable();
            $t->timestampsTz();
            $t->unique(['assessment_id', 'student_id']);
        });
        Schema::create('assessment_revisions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('assessment_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->json('snapshot');
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['assessment_id', 'version']);
        });
        Schema::create('behavior_observations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('student_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->date('date');
            $t->string('area', 40);
            $t->string('category', 40);
            $t->text('observation');
            $t->boolean('parent_visible')->default(false);
            $t->string('status', 20)->default('private');
            $t->foreignUuid('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
        });
        Schema::create('coordination_records', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('section_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignUuid('student_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignUuid('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('kind', 32);
            $t->string('title', 200);
            $t->text('notes');
            $t->unsignedSmallInteger('progress_percent')->nullable();
            $t->date('follow_up_on')->nullable();
            $t->string('status', 20)->default('open');
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT attendance_status CHECK(status IN ('present','absent','late','excused','school_holiday'))");
            DB::statement("ALTER TABLE attendance_registers ADD CONSTRAINT register_status CHECK(status IN ('draft','submitted'))");
            DB::statement('ALTER TABLE grading_rules ADD CONSTRAINT grading_percent CHECK(minimum_percent BETWEEN 0 AND 100)');
            DB::statement('ALTER TABLE assessments ADD CONSTRAINT max_marks_positive CHECK(maximum_marks > 0)');
            DB::statement('ALTER TABLE assessment_results ADD CONSTRAINT result_marks_valid CHECK(obtained_marks >= 0 AND obtained_marks <= maximum_marks AND percentage BETWEEN 0 AND 100)');
            DB::statement('ALTER TABLE coordination_records ADD CONSTRAINT syllabus_percent CHECK(progress_percent IS NULL OR progress_percent BETWEEN 0 AND 100)');
        }
    }

    public function down(): void
    {
        foreach (['coordination_records', 'behavior_observations', 'assessment_revisions', 'assessment_results', 'assessments', 'grading_rules', 'diary_revisions', 'diary_attachments', 'diary_students', 'diary_entries', 'attendance_revisions', 'attendance_records', 'attendance_registers', 'school_days'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
