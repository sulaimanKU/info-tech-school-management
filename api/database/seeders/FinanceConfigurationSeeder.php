<?php

namespace Database\Seeders;

use App\Support\Domain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FinanceConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $accounts = ['receivables' => ['Student receivables', 'asset'], 'cash' => ['Cash', 'asset'], 'bank' => ['Bank', 'asset'], 'tuition_revenue' => ['School fee revenue', 'revenue'], 'discount_expense' => ['Scholarships and discounts', 'expense'], 'adjustment_expense' => ['Approved fee adjustments', 'expense'], 'operating_expense' => ['School operating expenses', 'expense'], 'salary_expense' => ['Staff salary expense', 'expense'], 'payroll_payable' => ['Payroll payable', 'liability'], 'opening_equity' => ['Opening balance equity', 'equity']];
            foreach ($accounts as $code => [$name,$type]) {
                if (! DB::table('accounts')->where('code', $code)->exists()) {
                    Domain::insert('accounts', compact('code', 'name', 'type'));
                }
            }
            foreach (['cash' => ['Cash', false], 'bank_transfer' => ['Bank transfer', true]] as $code => [$name,$verify]) {
                if (! DB::table('payment_methods')->where('code', $code)->exists()) {
                    Domain::insert('payment_methods', ['code' => $code, 'name' => $name, 'account_id' => DB::table('accounts')->where('code', $code === 'cash' ? 'cash' : 'bank')->value('id'), 'requires_verification' => $verify]);
                }
            }
            foreach (['salaries', 'electricity', 'internet', 'stationery', 'cleaning', 'repairs', 'equipment', 'maintenance', 'events', 'other'] as $code) {
                if (! DB::table('expense_categories')->where('code', $code)->exists()) {
                    Domain::insert('expense_categories', ['code' => $code, 'name' => ucfirst($code)]);
                }
            }
            foreach (['sibling' => ['Sibling', 2000], 'imam_child' => ['Mosque imam children', 5000], 'orphan' => ['Orphan', 3000], 'merit' => ['Merit scholarship', 7000], 'special' => ['Management-approved discount', 10000]] as $code => [$name,$limit]) {
                if (! DB::table('discount_policies')->where('code', $code)->exists()) {
                    Domain::insert('discount_policies', ['code' => $code, 'name' => $name, 'maximum_basis_points' => $limit, 'applies_to' => 'tuition']);
                }
            }
            foreach (['fee_discount_stacking' => 'highest_only', 'expense_approval_threshold_minor' => 50000, 'school_weekdays' => [1, 2, 3, 4, 5, 6], 'class_position_enabled' => false] as $key => $value) {
                if (! DB::table('school_settings')->where('key', $key)->exists()) {
                    DB::table('school_settings')->insert(['key' => $key, 'value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            // Reference configuration, not fictional operational records. Dates are editable school setup defaults.
            $session = DB::table('academic_sessions')->where('name', '2026-27')->first();
            if (! $session) {
                $id = Domain::insert('academic_sessions', ['name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31']);
                $session = DB::table('academic_sessions')->where('id', $id)->first();
            }
            $tuition = ['Playgroup' => 1000, 'Nursery' => 1000, 'Prep' => 1200, 'Grade 1' => 1400, 'Grade 2' => 1600, 'Grade 3' => 1700, 'Grade 4' => 1800, 'Grade 5' => 2000, 'Grade 6' => 2400, 'Grade 7' => 2400, 'Grade 8' => 2600];
            foreach ($tuition as $name => $rupees) {
                $class = DB::table('classes')->where('name', $name)->first();
                if (! $class) {
                    continue;
                }foreach (['tuition' => $rupees, 'admission_promotion' => 3000] as $kind => $amount) {
                    if (! DB::table('fee_schedules')->where('academic_session_id', $session->id)->where('class_id', $class->id)->where('kind', $kind)->exists()) {
                        Domain::insert('fee_schedules', ['academic_session_id' => $session->id, 'class_id' => $class->id, 'kind' => $kind, 'amount_minor' => $amount * 100, 'effective_on' => $session->starts_on]);
                    }
                }
            }
        });
    }
}
