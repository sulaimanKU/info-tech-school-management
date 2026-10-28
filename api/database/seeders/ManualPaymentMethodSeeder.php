<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManualPaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(FinanceAccountSeeder::class);
        DB::transaction(function () {
            foreach (['cash' => ['Cash', 'cash', false], 'bank_transfer' => ['Bank Transfer', 'bank', false], 'cheque' => ['Cheque', 'bank', false], 'easypaisa' => ['Easypaisa', 'bank', false], 'jazzcash' => ['JazzCash', 'bank', false], 'other' => ['Other', 'cash', false]] as $code => [$name, $account, $verify]) {
                $accountId = DB::table('accounts')->where('code', $account)->value('id');
                if ($accountId) {
                    DB::table('payment_methods')->insertOrIgnore(['id' => (string) Str::uuid(), 'code' => $code, 'name' => $name, 'account_id' => $accountId, 'requires_verification' => $verify, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
    }
}
