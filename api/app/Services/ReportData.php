<?php

namespace App\Services;

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\ExamScheduleController;
use App\Http\Controllers\FeeController;
use App\Services\Finance\InvoiceBalance;
use App\Services\Finance\PayrollService;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ReportData
{
    public const TYPES = ['admission', 'student_id', 'voucher', 'receipt', 'annual_fee', 'attendance', 'academic', 'exam_schedule', 'salary_slip', 'financial', 'registration', 'communication'];

    public function __construct(private SchoolAccess $access, private AcademicSummary $academic) {}

    private function response($response): array
    {
        return $response->getData(true)['data'];
    }

    public function get(Request $r, string $type): array
    {
        $v = $r->validate(['student_id' => 'nullable|uuid', 'invoice_id' => 'nullable|uuid', 'payment_id' => 'nullable|uuid', 'payroll_item_id' => 'nullable|uuid', 'month' => 'nullable|date_format:Y-m', 'year' => 'nullable|integer|min:2000|max:2200', 'section_id' => 'nullable|uuid']);
        $filters = app(AcademicFilters::class);
        $fv = $filters->values($r, ['period' => 'nullable|date_format:Y-m', 'assigned_to' => 'nullable|uuid|exists:users,id']);
        if (in_array($type, ['attendance', 'academic', 'exam_schedule'])) {
            $this->access->academic($r->user());
        }
        $required = match ($type) {
            'admission','student_id','annual_fee','attendance','academic' => 'student_id', 'voucher' => 'invoice_id', 'receipt' => 'payment_id', 'salary_slip' => 'payroll_item_id', default => null
        };
        if ($required && empty($v[$required])) {
            Domain::fail($required, 'Select the required authorized record.');
        }
        if ($type === 'receipt' && $this->access->isParent($r->user()) && empty($v['student_id'])) {
            Domain::fail('student_id', 'Select your child for the receipt.');
        }
        $student = null;
        $meta = ['Generated' => now()->toIso8601String()];
        if (in_array($type, ['admission', 'student_id', 'annual_fee', 'attendance', 'academic'])) {
            $student = $this->access->student($r->user(), $v['student_id'] ?? '');
            $meta['Student'] = $student->full_name;
            $meta['Registration'] = $student->registration_number;
        }
        switch ($type) {
            case 'admission':
                abort_unless($this->access->isParent($r->user()) || $r->user()->hasPermission('students.manage'), 403);
                $rows = [array_intersect_key((array) $student, array_flip(['registration_number', 'full_name', 'admitted_on', 'gender', 'date_of_birth', 'father_name', 'address', 'previous_school', 'emergency_contact', 'status']))];
                break;
            case 'student_id':
                $e = DB::table('enrollments as e')->join('sections as s', 's.id', '=', 'e.section_id')->join('classes as c', 'c.id', '=', 's.class_id')->where('e.student_id', $student->id)->where('e.starts_on', '<=', now()->toDateString())->where(fn ($q) => $q->whereNull('e.ends_on')->orWhere('e.ends_on', '>=', now()->toDateString()))->first(['c.name as class', 's.name as section', 'e.roll_number']);
                $rows = [['registration_number' => $student->registration_number, 'full_name' => $student->full_name, ...(array) $e]];
                break;
            case 'voucher':
                $i = $this->response(app(FeeController::class)->show($r, $v['invoice_id'] ?? ''));
                $s = DB::table('students')->find($i['student_id']);
                $meta += ['Student' => $s->full_name, 'Registration' => $s->registration_number, 'Voucher' => $i['voucher_number'], 'Issued' => $i['issued_on'], 'Due' => $i['due_on'], 'Status' => $i['status']];
                $rows = $i['lines'];
                $rows[] = ['description' => 'Net balance', 'amount_minor' => $i['balance_minor']];
                foreach ($i['adjustments'] as $a) {
                    $rows[] = ['description' => 'Adjustment: '.$a['reason'], 'amount_minor' => $a['amount_minor']];
                }break;
            case 'receipt':
                $p = DB::table('payments')->where('id', $v['payment_id'] ?? '')->where('status', 'verified')->firstOrFail();
                $q = DB::table('payment_allocations as a')->join('fee_invoices as i', 'i.id', '=', 'a.fee_invoice_id')->join('students as s', 's.id', '=', 'i.student_id')->where('a.payment_id', $p->id);
                if ($this->access->isParent($r->user())) {
                    $student = $this->access->student($r->user(), $v['student_id'] ?? '');
                    $q->where('i.student_id', $student->id);
                    abort_unless((clone $q)->exists(), 404);
                    $meta['Student'] = $student->full_name;
                    $meta['Registration'] = $student->registration_number;
                } else {
                    $this->access->require($r->user(), 'finance.read');
                    abort_if((clone $q)->whereNotIn('i.student_id', $this->access->students($r->user())->select('students.id'))->exists(), 404);
                }
                $meta += ['Receipt' => $p->receipt_number, 'Paid' => $p->paid_on, 'Method' => DB::table('payment_methods')->where('id', $p->payment_method_id)->value('name'), 'Received by' => DB::table('users')->where('id', $p->recorded_by)->value('name')];
                if (! $this->access->isParent($r->user())) {
                    $meta['Reference'] = $p->reference ?? '';
                    $meta['Notes'] = $p->notes ?? '';
                }
                $rows = $q->get(['a.id', 's.id as student_id', 's.full_name', 's.registration_number', 'i.id as invoice_id', 'i.voucher_number', 'a.amount_minor'])->map(function ($a) {
                    $class = DB::table('enrollments as e')->join('sections as s', 's.id', '=', 'e.section_id')->join('classes as c', 'c.id', '=', 's.class_id')->where('e.student_id', $a->student_id)->orderByDesc('e.starts_on')->value('c.name');
                    $invoice = DB::table('fee_invoices')->find($a->invoice_id);

                    return ['student' => $a->full_name, 'registration_number' => $a->registration_number, 'class' => $class, 'voucher_number' => $a->voucher_number, 'amount_minor' => $a->amount_minor, 'balance_minor' => app(InvoiceBalance::class)->calculate($invoice)['balance_minor'], 'refunded_minor' => (int) DB::table('refund_allocations')->where('payment_allocation_id', $a->id)->sum('amount_minor')];
                })->all();
                break;
            case 'annual_fee':
                if (! $this->access->isParent($r->user())) {
                    $this->access->require($r->user(), 'finance.read');
                }$year = $v['year'] ?? now()->year;
                $meta['Calendar year'] = $year;
                $rows = DB::table('fee_invoices')->where('student_id', $student->id)->where('period', 'like', $year.'-%')->orderBy('period')->get()->map(fn ($i) => ['voucher_number' => $i->voucher_number, 'period' => $i->period, 'due_on' => $i->due_on, ...app(InvoiceBalance::class)->calculate($i)])->all();
                break;
            case 'attendance':
                $month = $v['month'] ?? now()->format('Y-m');
                $summary = $this->academic->attendance($student->id, $month, $r->user());
                $meta += ['Month' => $month, 'Configured school days' => $summary['school_days'], 'Recorded days' => $summary['recorded_days'], 'Percentage' => $summary['attendance_percentage'] ?? 'Not recorded', 'Provisional' => $summary['provisional'] ? 'Yes' : 'No'];
                $rows = $summary['items']->map(fn ($i) => (array) $i)->all();
                break;
            case 'academic':
                $a = $this->response(app(AssessmentController::class)->student($r, $student->id));
                $meta['Overall percentage'] = $a['overall_percentage'] ?? 'Not recorded';
                if ($a['selected_period'] ?? null) {
                    $p = $a['selected_period'];
                    $meta += ['Assessment' => $p['title'], 'Session' => $p['session_name'], 'Class' => $p['class_name'], 'Section' => $p['section_name'], 'Grade' => $p['grade'] ?? 'Not recorded', 'Published' => $p['published_at'] ?? 'Not recorded'];
                }
                $rows = array_map(fn ($i) => array_intersect_key($i, array_flip(['title', 'type', 'date', 'subject_name', 'maximum_marks', 'obtained_marks', 'percentage', 'grade', 'remarks', 'class_position', 'teacher_name', 'published_at'])), $a['items']);
                break;
            case 'exam_schedule':
                $rows = array_map(fn ($i) => array_intersect_key($i, array_flip(['title', 'class_name', 'section_name', 'subject_name', 'date', 'starts_at', 'ends_at', 'room', 'status'])), $this->response(app(ExamScheduleController::class)->index($r))['items']);
                break;
            case 'salary_slip':
                $item = DB::table('payroll_items as i')->join('payroll_periods as p', 'p.id', '=', 'i.payroll_period_id')->join('staff_profiles as s', 's.id', '=', 'i.staff_profile_id')->join('users as u', 'u.id', '=', 's.user_id')->where('i.id', $v['payroll_item_id'] ?? '')->whereIn('p.status', ['approved', 'paid'])->first(['i.*', 'p.period', 'p.paid_on', 'p.id as payroll_period_id', 's.user_id', 's.employee_number', 'u.name']);
                abort_if(! $item, 404);
                abort_unless($item->user_id === $r->user()->id || $r->user()->hasPermission('payroll.read'), 403);
                $details = app(PayrollService::class)->details(DB::table('payroll_periods')->find($item->payroll_period_id));
                $amounts = $details['items']->firstWhere('id', $item->id);
                abort_unless($amounts['paid_minor'] > 0, 404);
                $item->paid_minor = $amounts['paid_minor'];
                $item->balance_minor = $amounts['balance_minor'];
                $rows = [array_intersect_key((array) $item, array_flip(['name', 'employee_number', 'period', 'paid_on', 'base_salary_minor', 'adjustment_minor', 'net_salary_minor', 'paid_minor', 'balance_minor']))];
                break;
            case 'financial':
                $this->access->require($r->user(), 'accounting.read');
                $this->access->require($r->user(), 'reports.export');
                $this->access->require($r->user(), 'students.read.all');
                $meta['Report'] = 'Trial balance';
                if (isset($fv['from'])) {
                    $meta['From'] = $fv['from'];
                }if (isset($fv['to'])) {
                    $meta['To'] = $fv['to'];
                }
                $rows = DB::table('accounts as a')->leftJoin('journal_lines as l', function ($j) use ($fv) {
                    $j->on('l.account_id', '=', 'a.id')->whereIn('l.journal_entry_id', DB::table('journal_entries')->when($fv['from'] ?? null, fn ($q, $d) => $q->whereDate('date', '>=', $d))->when($fv['to'] ?? null, fn ($q, $d) => $q->whereDate('date', '<=', $d))->select('id'));
                })->groupBy('a.id', 'a.code', 'a.name', 'a.type')->orderBy('a.code')->selectRaw('a.code,a.name,a.type,coalesce(sum(l.debit_minor),0) as debit_minor,coalesce(sum(l.credit_minor),0) as credit_minor')->get()->map(fn ($i) => (array) $i)->all();
                break;
            case 'registration':
                $this->access->require($r->user(), 'reports.export');
                $q = $filters->students($this->access->students($r->user()), $r, $fv);
                if (isset($v['section_id'])) {
                    $this->access->section($r->user(), $v['section_id']);
                    $q->whereIn('students.id', DB::table('enrollments')->where('section_id', $v['section_id'])->select('student_id'));
                }$rows = $q->orderBy('registration_number')->limit(5001)->get(['registration_number', 'full_name', 'admitted_on', 'status'])->map(fn ($i) => (array) $i)->all();
                break;
            case 'communication':
                if (! $this->access->isParent($r->user())) {
                    $this->access->require($r->user(), 'communication.manage');
                }$q = app(TicketAccess::class)->query($r->user());
                $students = $filters->students($this->access->students($r->user()), $r, array_diff_key($fv, array_flip(['status'])));
                $q->whereIn('t.student_id', $students->select('students.id'));
                if (isset($fv['assigned_to'])) {
                    $q->where('t.assigned_to', $fv['assigned_to']);
                }
                if (isset($fv['student_id'])) {
                    $q->where('t.student_id', $fv['student_id']);
                }
                if (isset($fv['from'])) {
                    $q->whereDate('t.created_at', '>=', $fv['from']);
                }if (isset($fv['to'])) {
                    $q->whereDate('t.created_at', '<=', $fv['to']);
                }
                if (isset($fv['status'])) {
                    $q->where('t.status', $fv['status']);
                }
                $rows = $q->join('students as s', 's.id', '=', 't.student_id')->orderByDesc('t.created_at')->limit(5001)->get(['t.number', 's.registration_number', 't.category', 't.subject', 't.status', 't.created_at', 't.resolved_at', 't.closed_at'])->map(fn ($i) => (array) $i)->all();
                break;
            default:abort(404);
        }
        if (count($rows) > 5000) {
            Domain::fail('report', 'Narrow the report to at most 5000 rows.');
        }

        return ['title' => ucwords(str_replace('_', ' ', $type)), 'meta' => $meta, 'rows' => $rows, 'photo_file_id' => $type === 'student_id' ? $student?->photo_file_id : null];
    }
}
