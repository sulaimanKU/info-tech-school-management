<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Fresh installations get the grant from MobilePermissionSeeder after roles exist.
        if (! DB::table('roles')->exists() || DB::table('permissions')->where('code', 'assignments.manage')->exists()) {
            return;
        }
        DB::transaction(function () {
            $id = (string) Str::uuid();
            DB::table('permissions')->insert(['id' => $id, 'code' => 'assignments.manage', 'name' => 'Manage teacher assignments in authorized sections', 'created_at' => now(), 'updated_at' => now()]);
            foreach (DB::table('roles')->whereIn('code', ['super_admin', 'school_administrator', 'coordinator'])->pluck('id') as $role) {
                DB::table('permission_role')->insertOrIgnore(['role_id' => $role, 'permission_id' => $id]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $id = DB::table('permissions')->where('code', 'assignments.manage')->value('id');
            if ($id) {
                DB::table('permission_role')->where('permission_id', $id)->delete();
                DB::table('permissions')->where('id', $id)->delete();
            }
        });
    }
};
