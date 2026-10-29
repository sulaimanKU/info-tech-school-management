<?php

namespace Tests\Feature;

use App\Services\Finance\PayrollService;
use App\Support\Domain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MobileTestCase;

class BusinessIdentifierTest extends MobileTestCase
{
    public function test_codes_are_unique_stable_and_backfill_is_resumable_without_changing_relationships(): void
    {
        $teacher = $this->teacher();
        $code = $teacher->account_code;
        $this->assertNotSame($this->admin->account_code, $code);
        $student = $this->student();
        $request = Domain::insert('admission_requests', ['student_id' => $student, 'user_id' => $this->admin->id, 'payload_hash' => hash('sha256', 'fictional fixture')]);
        DB::table('users')->where('id', $teacher->id)->update(['account_code' => null]);
        $migration = require database_path('migrations/2026_10_29_000019_add_readable_business_codes.php');
        $migration->up();
        $filled = $teacher->fresh()->account_code;
        $admission = DB::table('admission_requests')->find($request)->admission_number;
        $this->assertMatchesRegularExpression('/^ACC-[0-9]{6,}$/', $filled);
        $this->assertMatchesRegularExpression('/^ADM-[0-9]{6,}$/', $admission);
        $migration->up();
        $this->assertSame($filled, $teacher->fresh()->account_code);
        $this->assertSame($admission, DB::table('admission_requests')->find($request)->admission_number);
        $this->assertSame($teacher->id, DB::table('staff_profiles')->where('user_id', $teacher->id)->sole()->user_id);
        $this->assertSame($student, DB::table('admission_requests')->find($request)->student_id);
    }

    public function test_admission_retry_returns_the_same_readable_admission_number(): void
    {
        $this->asUser($this->admin);
        $input = [...$this->admission(), 'request_id' => (string) Str::uuid()];
        $data = $this->postJson('/api/v1/students', $input)->assertCreated()->json('data');
        $this->assertMatchesRegularExpression('/^ADM-[0-9]{6,}$/', $data['admission_number']);
        $this->postJson('/api/v1/students', $input)->assertCreated()->assertJsonPath('data.admission_number', $data['admission_number'])->assertJsonPath('data.registration_number', $data['registration_number']);
        $this->assertDatabaseCount('admission_requests', 1);
    }

    public function test_payroll_and_slip_references_do_not_depend_on_mutable_employee_number(): void
    {
        $teacher = $this->teacher();
        $profile = DB::table('staff_profiles')->where('user_id', $teacher->id)->sole();
        $period = Domain::insert('payroll_periods', ['period' => '2026-10', 'total_minor' => 100000, 'status' => 'draft', 'created_by' => $this->admin->id]);
        Domain::insert('payroll_items', ['payroll_period_id' => $period, 'staff_profile_id' => $profile->id, 'base_salary_minor' => 100000, 'adjustment_minor' => 0, 'net_salary_minor' => 100000]);
        $service = app(PayrollService::class);
        $first = $service->details(DB::table('payroll_periods')->find($period));
        $this->assertSame('INF-SG-PR-2026-10', $first['payroll_number']);
        $this->assertSame('INF-SG-SL-2026-10-'.$teacher->account_code, $first['items'][0]['slip_number']);
        DB::table('staff_profiles')->where('id', $profile->id)->update(['employee_number' => 'STF-0001']);
        $this->assertSame($first['items'][0]['slip_number'], $service->details(DB::table('payroll_periods')->find($period))['items'][0]['slip_number']);
    }
}
