<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $t) {
            $t->foreignUuid('class_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('target_role_code', 40)->nullable();
        });

        Schema::create('coordinator_assignments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('staff_profile_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('section_id')->constrained()->restrictOnDelete();
            $t->boolean('is_active')->default(true);
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->timestampsTz();

            $t->index(
                ['staff_profile_id', 'section_id', 'is_active'],
                'coordinator_staff_section_active_idx'
            );
        });

        Schema::table('parent_student_links', fn (Blueprint $t) =>
            $t->timestampTz('verified_at')->nullable()->change()
        );

        Schema::table('teacher_assignments', function (Blueprint $t) {
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX active_coordinator_section
                ON coordinator_assignments(staff_profile_id,section_id)
                WHERE is_active = true'
            );

            DB::statement(
                'ALTER TABLE coordinator_assignments
                ADD CONSTRAINT coordinator_assignment_dates
                CHECK(ends_on IS NULL OR starts_on IS NULL OR ends_on >= starts_on)'
            );

            DB::statement(
                'ALTER TABLE teacher_assignments
                ADD CONSTRAINT teacher_assignment_dates
                CHECK(ends_on IS NULL OR starts_on IS NULL OR ends_on >= starts_on)'
            );
        }

        $role = DB::table('roles')
            ->where('code', 'coordinator')
            ->value('id');

        $permission = DB::table('permissions')
            ->where('code', 'students.read.all')
            ->value('id');

        if ($role && $permission) {
            DB::table('permission_role')
                ->where('role_id', $role)
                ->where('permission_id', $permission)
                ->delete();
        }

        if (! DB::table('roles')->exists()) {
            return;
        }

        // Add only the new permissions. Never restore previously revoked legacy grants.
        foreach ([
            'students.read' => [
                'super_admin',
                'school_administrator',
                'coordinator',
                'accountant_clerk',
                'teacher',
            ],
            'academics.read' => [
                'super_admin',
                'school_administrator',
                'coordinator',
                'teacher',
            ],
            'rbac.manage' => [
                'super_admin',
            ],
        ] as $code => $roles) {
            $permission = DB::table('permissions')
                ->where('code', $code)
                ->value('id');

            if (! $permission) {
                $permission = (string) Str::uuid();

                DB::table('permissions')->insert([
                    'id' => $permission,
                    'code' => $code,
                    'name' => $code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (
                DB::table('roles')
                    ->whereIn('code', $roles)
                    ->pluck('id') as $role
            ) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $role,
                    'permission_id' => $permission,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $t) {
            $t->dropConstrainedForeignId('class_id');
            $t->dropColumn('target_role_code');
        });

        Schema::dropIfExists('coordinator_assignments');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE teacher_assignments
                DROP CONSTRAINT IF EXISTS teacher_assignment_dates'
            );
        }

        Schema::table(
            'teacher_assignments',
            fn (Blueprint $t) => $t->dropColumn(['starts_on', 'ends_on'])
        );

        // Never restore a school-wide grant automatically during rollback.
    }
};