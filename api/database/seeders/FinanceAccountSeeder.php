<?php

namespace Database\Seeders;

use App\Support\Domain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FinanceAccountSeeder extends Seeder
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

        });
    }
}
