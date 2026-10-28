<?php

namespace Tests\Support;

use App\Support\Domain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class StaffAccountValidationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || DB::connection()->getDatabaseName() !== 'infotech_school_test') {
            throw new \RuntimeException('Fictional isolated validation database only.');
        }
        $admin = DB::table('users')->where('email', 'md@fictional.example.test')->value('id');
        $profiles = DB::table('staff_profiles')->get();
        $period = Domain::insert('payroll_periods', ['period' => '2026-09', 'total_minor' => $profiles->count() * 100000, 'status' => 'paid', 'paid_on' => '2026-10-01', 'created_by' => $admin]);
        foreach ($profiles as $profile) {
            Domain::insert('payroll_items', ['payroll_period_id' => $period, 'staff_profile_id' => $profile->id, 'base_salary_minor' => 100000, 'adjustment_minor' => 0, 'net_salary_minor' => 100000]);
        }
    }
}
