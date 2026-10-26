<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MobilePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $grants = [
            'super_admin' => ['rbac.manage', 'students.read', 'academics.read', 'students.read.all', 'students.manage', 'guardians.manage', 'school.manage', 'staff.manage', 'academics.write', 'academics.review', 'attendance.correct', 'finance.read', 'finance.write', 'finance.approve', 'communication.manage', 'announcements.manage', 'reports.export', 'imports.manage'],
            'school_administrator' => ['students.read', 'academics.read', 'students.read.all', 'students.manage', 'guardians.manage', 'school.manage', 'staff.manage', 'academics.write', 'academics.review', 'attendance.correct', 'finance.read', 'communication.manage', 'announcements.manage', 'reports.export', 'imports.manage'],
            'coordinator' => ['students.read', 'academics.read', 'academics.write', 'academics.review', 'attendance.correct', 'communication.manage', 'announcements.manage', 'reports.export'],
            'accountant_clerk' => ['students.read', 'students.read.all', 'finance.read', 'finance.write', 'reports.export'],
            'teacher' => ['students.read', 'academics.read', 'academics.write'], 'parent_guardian' => [],
        ];
        DB::transaction(function () use ($grants) {
            foreach ($grants as $code => $permissions) {
                $role = Role::where('code', $code)->firstOrFail();
                foreach ($permissions as $permission) {
                    $p = DB::table('permissions')->where('code', $permission)->first();
                    $id = $p?->id ?? (string) Str::uuid();
                    if (! $p) {
                        DB::table('permissions')->insert(['id' => $id, 'code' => $permission, 'name' => $permission, 'created_at' => now(), 'updated_at' => now()]);
                    }
                    DB::table('permission_role')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $id]);
                }
            }
        });
    }
}
