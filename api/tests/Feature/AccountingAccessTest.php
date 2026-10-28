<?php

namespace Tests\Feature;

use App\Services\Finance\JournalService;
use App\Support\Domain;
use Database\Seeders\FinanceConfigurationSeeder;
use Database\Seeders\MobilePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\MobileTestCase;

class AccountingAccessTest extends MobileTestCase
{
    public function test_trial_balance_without_dates_includes_the_complete_posted_ledger(): void
    {
        $this->seed(FinanceConfigurationSeeder::class);
        $journal = app(JournalService::class);
        foreach (['2025-12-31' => 12000, '2026-10-01' => 35000] as $date => $amount) {
            $journal->post($this->admin, 'fixture:'.$date, $date, 'Fictional journal', 'fixture', $this->admin->id, [
                ['account_id' => $journal->account('salary_expense'), 'debit_minor' => $amount],
                ['account_id' => $journal->account('payroll_payable'), 'credit_minor' => $amount],
            ]);
        }
        $this->asUser($this->user('accountant_clerk'))->getJson('/api/v1/finance/trial-balance')->assertOk()
            ->assertJsonPath('data.debit_minor', 47000)->assertJsonPath('data.credit_minor', 47000)
            ->assertJsonPath('data.balanced', true)->assertJsonCount(2, 'data.items');
        $this->getJson('/api/v1/finance/trial-balance?from=2026-01-01&to=2026-12-31')->assertOk()
            ->assertJsonPath('data.debit_minor', 35000)->assertJsonPath('data.credit_minor', 35000);
    }

    public function test_trial_balance_rejects_partial_invalid_and_reversed_ranges_and_unauthorized_users(): void
    {
        $this->asUser($this->admin);
        foreach (['from=2026-01-01', 'to=2026-12-31', 'from=invalid&to=2026-12-31', 'from=2026-12-31&to=2026-01-01'] as $query) {
            $this->getJson('/api/v1/finance/trial-balance?'.$query)->assertUnprocessable();
        }
        $this->asUser($this->teacher())->getJson('/api/v1/finance/trial-balance')->assertForbidden();
    }

    public function test_administrator_can_configure_fees_but_cannot_write_or_approve_financial_transactions(): void
    {
        $this->seed(FinanceConfigurationSeeder::class);
        $this->seed(MobilePermissionSeeder::class);
        $admin = $this->user('school_administrator');
        $this->assertTrue($admin->hasPermission('finance.configure'));
        $this->assertFalse($admin->hasPermission('finance.write'));
        $this->assertFalse($admin->hasPermission('finance.approve'));
        $this->asUser($admin)->postJson('/api/v1/finance/fee-schedules', [
            'academic_session_id' => $this->session,
            'class_id' => DB::table('sections')->where('id', $this->section)->value('class_id'),
            'kind' => 'tuition', 'amount_minor' => 10000, 'effective_on' => '2026-01-01',
        ])->assertCreated();
        foreach (['payroll', 'payments', 'expenses', 'compensation', 'payroll-adjustments'] as $path) {
            $this->postJson('/api/v1/finance/'.$path, [])->assertForbidden();
        }
        $period = Domain::insert('payroll_periods', ['period' => '2026-09', 'total_minor' => 10000, 'status' => 'draft', 'created_by' => $this->admin->id]);
        $this->postJson('/api/v1/finance/payroll/'.$period.'/approve')->assertForbidden();
        $this->postJson('/api/v1/finance/payroll/'.$period.'/pay', [])->assertForbidden();
        $this->assertDatabaseHas('payroll_periods', ['id' => $period, 'status' => 'draft']);
        $this->assertDatabaseCount('journal_entries', 0);
        $clerk = $this->user('accountant_clerk');
        $this->assertFalse($clerk->hasPermission('finance.configure'));
        $this->assertTrue($clerk->hasPermission('finance.write'));
        $this->asUser($clerk)->postJson('/api/v1/finance/fee-schedules', [])->assertForbidden();
    }
}
