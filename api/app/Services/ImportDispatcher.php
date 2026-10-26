<?php

namespace App\Services;

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\FeeController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\StudentController;
use App\Models\User;
use App\Services\Finance\FeeService;
use App\Support\Domain;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ImportDispatcher
{
    public const FIELDS = [
        'students' => ['full_name', 'admitted_on', 'date_of_birth', 'gender', 'father_name', 'address', 'previous_school', 'emergency_contact', 'fee_category_id', 'section_id', 'roll_number'],
        'parents' => ['full_name', 'cnic', 'mobile', 'mobile_verified', 'student_id', 'relationship', 'is_primary'],
        'staff' => ['user_id', 'employee_number', 'joined_on', 'designation'],
        'classes' => ['name', 'sort_order'],
        'fee_balances' => ['student_id', 'academic_session_id', 'period', 'issued_on', 'due_on', 'amount_minor', 'description'],
        'payment_history' => ['fee_invoice_id', 'amount_minor', 'payment_method_id', 'paid_on', 'reference'],
        'attendance' => ['section_id', 'date', 'student_id', 'status'],
        'assessment_marks' => ['assessment_id', 'student_id', 'obtained_marks', 'remarks'],
        'biometric' => ['device_code', 'external_id', 'punched_at', 'direction']];

    private function request(User $user, array $data): Request
    {
        $r = Request::create('/import', 'POST', $data);
        $r->setUserResolver(fn () => $user);

        return $r;
    }

    public function apply(User $user, string $kind, array $rows, string $batch): array
    {
        $result = [];
        if (in_array($kind, ['attendance', 'assessment_marks'])) {
            $groups = [];
            foreach ($rows as $row) {
                $key = $kind === 'attendance' ? ($row['section_id'] ?? '').':'.($row['date'] ?? '') : ($row['assessment_id'] ?? '');
                $groups[$key][] = $row;
            }
            foreach ($groups as $key => $group) {
                $first = $group[0];
                if ($kind === 'attendance') {
                    $old = DB::table('attendance_registers')->where('section_id', $first['section_id'] ?? null)->where('date', $first['date'] ?? null)->first();
                    if ($old) {
                        Domain::fail('rows', 'An attendance register already exists; review and correct it manually.');
                    }
                    $res = app(AttendanceController::class)->save($this->request($user, ['section_id' => $first['section_id'] ?? null, 'date' => $first['date'] ?? null, 'status' => 'submitted', 'version' => 0, 'request_id' => Str::uuid()->toString(), 'records' => array_map(fn ($v) => ['student_id' => $v['student_id'] ?? null, 'status' => $v['status'] ?? null], $group)]));
                } else {
                    $a = DB::table('assessments')->where('id', $first['assessment_id'] ?? null)->firstOrFail();
                    $res = app(AssessmentController::class)->marks($this->request($user, ['version' => $a->version, 'status' => 'submitted', 'results' => array_map(fn ($v) => ['student_id' => $v['student_id'] ?? null, 'obtained_marks' => $v['obtained_marks'] ?? null, 'remarks' => $v['remarks'] ?? null], $group)]), $a->id);
                }$result[] = $res->getData(true)['data'];
            }

            return $result;
        }
        foreach ($rows as $index => $v) {
            $request = $this->request($user, $v);
            $res = match ($kind) {
                'students' => app(StudentController::class)->store($request),
                'parents' => $this->parent($request, $v),
                'staff' => app(CatalogController::class)->store($request, 'staff'),
                'classes' => app(CatalogController::class)->store($request, 'classes'),
                'fee_balances' => app(FeeController::class)->store($this->request($user, [...$v, 'kind' => 'opening_balance', 'request_id' => Str::uuid()->toString(), 'charges' => [['description' => $v['description'] ?? 'Imported opening balance', 'amount_minor' => $v['amount_minor'] ?? null]]]), app(FeeService::class)),
                'payment_history' => app(PaymentController::class)->store($this->request($user, [...$v, 'request_id' => Str::uuid()->toString(), 'allocations' => [['fee_invoice_id' => $v['fee_invoice_id'] ?? null, 'amount_minor' => $v['amount_minor'] ?? null]]])),
                'biometric' => $this->punch($user, $v, $batch),
                default => abort(422)
            };
            $result[] = $res->getData(true)['data'];
        }

        return $result;
    }

    private function parent(Request $r, array $v)
    {
        $controller = app(GuardianController::class);
        $res = $controller->store($r);
        if (! empty($v['student_id'])) {
            $controller->link($this->request($r->user(), ['student_id' => $v['student_id'], 'relationship' => $v['relationship'] ?? 'guardian', 'is_primary' => filter_var($v['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN)]), $res->getData(true)['data']['id']);
        }

        return $res;
    }

    private function punch(User $u, array $v, string $batch)
    {
        app(SchoolAccess::class)->require($u, 'academics.write');
        $v = validator($v, ['device_code' => 'required|string|max:80', 'external_id' => 'required|string|max:80', 'punched_at' => 'required|date_format:Y-m-d H:i:s|before_or_equal:now', 'direction' => 'required|in:in,out'])->validate();
        $m = DB::table('biometric_mappings')->where('device_code', $v['device_code'])->where('external_id', $v['external_id'])->first();
        if (! $m) {
            Domain::fail('external_id', 'Unmatched biometric ID; configure a mapping first.');
        }
        app(SchoolAccess::class)->student($u, $m->student_id);
        if (DB::table('biometric_punches')->where('student_id', $m->student_id)->where('device_code', $v['device_code'])->where('punched_at', $v['punched_at'])->where('direction', $v['direction'])->exists()) {
            Domain::fail('punched_at', 'Duplicate punch.');
        }

        return Domain::response(['id' => Domain::insert('biometric_punches', ['student_id' => $m->student_id, 'import_batch_id' => $batch, 'device_code' => $v['device_code'], 'punched_at' => Carbon::createFromFormat('Y-m-d H:i:s', $v['punched_at'], 'Asia/Karachi')->utc(), 'direction' => $v['direction']])]);
    }
}
