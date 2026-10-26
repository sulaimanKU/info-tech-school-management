<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->string('subject_type', 64)->nullable();
            $t->uuid('subject_id')->nullable()->index();
            $t->json('context')->nullable();
        });
        Schema::create('registration_sequences', function (Blueprint $t) {
            $t->unsignedSmallInteger('year')->primary();
            $t->unsignedInteger('next_number')->default(1);
        });
        Schema::create('fee_categories', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name')->unique();
            $t->timestampsTz();
        });
        Schema::create('students', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('registration_number')->unique();
            $t->date('admitted_on');
            $t->string('full_name');
            $t->string('gender', 16);
            $t->date('date_of_birth');
            $t->string('father_name')->nullable();
            $t->text('address')->nullable();
            $t->string('previous_school')->nullable();
            $t->string('emergency_contact', 30)->nullable();
            $t->string('status', 24)->default('active')->index();
            $t->foreignUuid('fee_category_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('updated_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
        });
        Schema::create('enrollments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('student_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('section_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('academic_session_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('roll_number');
            $t->date('starts_on');
            $t->date('ends_on')->nullable();
            $t->string('status', 24)->default('active');
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['student_id', 'academic_session_id', 'section_id', 'starts_on']);
            $t->index(['section_id', 'status']);
        });
        Schema::create('student_transitions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('student_id')->constrained()->restrictOnDelete();
            $t->string('type', 24);
            $t->foreignUuid('from_enrollment_id')->nullable()->constrained('enrollments')->restrictOnDelete();
            $t->foreignUuid('to_enrollment_id')->nullable()->constrained('enrollments')->restrictOnDelete();
            $t->date('effective_on');
            $t->text('reason')->nullable();
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at')->useCurrent();
        });
        Schema::create('parent_guardians', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->unique()->constrained()->restrictOnDelete();
            $t->string('full_name');
            $t->text('cnic_ciphertext');
            $t->char('cnic_lookup', 64)->unique();
            $t->char('cnic_last4', 4);
            $t->text('mobile_ciphertext');
            $t->timestampTz('mobile_verified_at')->nullable();
            $t->foreignUuid('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
        });
        Schema::create('parent_student_links', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('parent_guardian_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('student_id')->constrained()->restrictOnDelete();
            $t->string('relationship', 32);
            $t->boolean('is_primary')->default(false);
            $t->boolean('is_active')->default(true);
            $t->foreignUuid('verified_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('verified_at');
            $t->timestampsTz();
            $t->unique(['parent_guardian_id', 'student_id']);
        });
        Schema::create('staff_profiles', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->unique()->constrained()->restrictOnDelete();
            $t->string('employee_number')->unique();
            $t->date('joined_on');
            $t->string('designation');
            $t->boolean('is_active')->default(true);
            $t->timestampsTz();
        });
        Schema::create('class_subject_assignments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('class_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('subject_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('academic_session_id')->constrained()->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['class_id', 'subject_id', 'academic_session_id'], 'class_subject_session_unique');
        });
        Schema::create('teacher_assignments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('staff_profile_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('section_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('subject_id')->nullable()->constrained()->restrictOnDelete();
            $t->boolean('is_class_teacher')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestampsTz();
            $t->index(['staff_profile_id', 'section_id', 'is_active']);
        });
        Schema::create('otp_challenges', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('parent_guardian_id')->nullable()->constrained()->cascadeOnDelete();
            $t->char('lookup_hash', 64)->index();
            $t->string('otp_hash');
            $t->timestampTz('expires_at')->index();
            $t->timestampTz('consumed_at')->nullable();
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestampsTz();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE students ADD CONSTRAINT student_status CHECK(status IN ('active','withdrawn','transferred'))");
            DB::statement("ALTER TABLE students ADD CONSTRAINT student_gender CHECK(gender IN ('male','female','other'))");
            DB::statement('ALTER TABLE students ADD CONSTRAINT student_birth CHECK(date_of_birth <= admitted_on)');
            DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollment_dates CHECK(ends_on IS NULL OR ends_on >= starts_on)');
            DB::statement("CREATE UNIQUE INDEX enrollment_active_student ON enrollments(student_id) WHERE status = 'active'");
            DB::statement("CREATE UNIQUE INDEX enrollment_active_roll ON enrollments(section_id,roll_number) WHERE status = 'active'");
            DB::statement('CREATE UNIQUE INDEX primary_guardian_unique ON parent_student_links(student_id) WHERE is_primary = true AND is_active = true');
            DB::statement('CREATE UNIQUE INDEX class_teacher_unique ON teacher_assignments(section_id) WHERE is_class_teacher = true AND is_active = true');
            DB::statement('CREATE UNIQUE INDEX subject_teacher_unique ON teacher_assignments(staff_profile_id,section_id,subject_id) WHERE is_active = true');
        }
    }

    public function down(): void
    {
        foreach (['otp_challenges', 'teacher_assignments', 'class_subject_assignments', 'staff_profiles', 'parent_student_links', 'parent_guardians', 'student_transitions', 'enrollments', 'students', 'fee_categories', 'registration_sequences'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('audit_logs', fn (Blueprint $t) => $t->dropColumn(['subject_type', 'subject_id', 'context']));
    }
};
