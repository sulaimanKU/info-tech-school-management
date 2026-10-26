<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $roles = ['super_admin' => 'Super Admin / Managing Director', 'school_administrator' => 'School Administrator', 'coordinator' => 'Coordinator', 'accountant_clerk' => 'Accountant / Clerk', 'teacher' => 'Teacher', 'parent_guardian' => 'Parent / Guardian'];
            foreach ($roles as $code => $name) {
                Role::updateOrCreate(['code' => $code], ['name' => $name]);
            }
            DB::table('permissions')->updateOrInsert(['code' => 'settings.read'], ['id' => DB::table('permissions')->where('code', 'settings.read')->value('id') ?? (string) Str::uuid(), 'name' => 'Read school settings', 'created_at' => now(), 'updated_at' => now()]);
            $permission = DB::table('permissions')->where('code', 'settings.read')->value('id');
            foreach (['super_admin', 'school_administrator'] as $role) {
                DB::table('permission_role')->insertOrIgnore(['permission_id' => $permission, 'role_id' => Role::where('code', $role)->value('id')]);
            }
            foreach (['school_name' => 'INFO-TECH High School & Educational Campus', 'campus' => 'Sangao Campus', 'location' => 'Mardan, Khyber Pakhtunkhwa, Pakistan', 'timezone' => 'Asia/Karachi', 'currency' => 'PKR'] as $key => $value) {
                DB::table('school_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach (['Playgroup', 'Nursery', 'Prep', ...array_map(fn ($n) => 'Grade '.$n, range(1, 8))] as $order => $name) {
                DB::table('classes')->updateOrInsert(['name' => $name], ['id' => DB::table('classes')->where('name', $name)->value('id') ?? (string) Str::uuid(), 'sort_order' => $order, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }
}
