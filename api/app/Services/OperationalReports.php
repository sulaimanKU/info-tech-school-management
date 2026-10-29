<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class OperationalReports
{
    public const TYPES = ['enrollment', 'teacher_assignments', 'class_attendance', 'class_results', 'payment_register', 'payroll_summary'];

    public function rows(Request $r, string $type): array
    {
        $access = app(SchoolAccess::class);
        $access->require($r->user(), 'reports.export');
        $filters = app(AcademicFilters::class);
        $statuses = match ($type) {
            'enrollment' => 'active,completed,withdrawn,transferred',
            'teacher_assignments' => 'active,inactive',
            'class_attendance' => 'present,absent,late,excused,school_holiday',
            'payment_register' => 'pending,verified,canceled',
            default => null,
        };
        $v = $filters->values($r, ['period' => 'nullable|date_format:Y-m', ...($statuses ? ['status' => 'nullable|in:'.$statuses] : [])]);
        $students = $type === 'payroll_summary' ? null : $filters->students($access->students($r->user()), $r, array_diff_key($v, ['status' => true]));
        $sections = $filters->sections($r, $v);
        if (in_array($type, ['teacher_assignments', 'class_attendance', 'class_results'])) {
            $access->academic($r->user());
            abort_if($access->isParent($r->user()), 403);
        }
        if ($type === 'enrollment') {
            $q = DB::table('enrollments as e')->join('students as s', 's.id', '=', 'e.student_id')->join('sections as sec', 'sec.id', '=', 'e.section_id')->join('classes as c', 'c.id', '=', 'sec.class_id')->join('academic_sessions as y', 'y.id', '=', 'e.academic_session_id')
                ->whereIn('e.student_id', $students->select('students.id'))->whereIn('e.section_id', $sections->select('sections.id'));
            $q->when($v['from'] ?? null, fn ($q, $date) => $q->where('e.starts_on', '>=', $date))->when($v['to'] ?? null, fn ($q, $date) => $q->where('e.starts_on', '<=', $date));
            if (isset($v['status'])) {
                $q->where('e.status', $v['status']);
            }
            $rows = $q->orderBy('y.starts_on')->orderBy('c.sort_order')->orderBy('e.roll_number')->limit(5001)->get(['s.registration_number', 's.full_name', 'y.name as session', 'c.name as class', 'sec.name as section', 'e.roll_number', 'e.starts_on', 'e.ends_on', 'e.status']);
        } elseif ($type === 'teacher_assignments') {
            $q = app(TeachingAssignments::class)->query($r->user())->whereIn('ta.section_id', $sections->select('sections.id'));
            if (! $r->user()->hasPermission('school.manage') && ! $r->user()->hasPermission('assignments.manage')) {
                $q->where('p.user_id', $r->user()->id);
            }
            if (! empty($v['search'])) {
                $q->where(fn ($q) => $q->where('u.name', 'like', '%'.$v['search'].'%')->orWhere('u.account_code', 'like', '%'.$v['search'].'%')->orWhere('p.employee_number', 'like', '%'.$v['search'].'%'));
            }
            $q->when($v['status'] ?? null, fn ($q, $status) => $q->where('ta.is_active', $status === 'active'))
                ->when($v['subject_id'] ?? null, fn ($q, $id) => $q->where('ta.subject_id', $id))
                ->when($v['from'] ?? null, fn ($q, $date) => $q->where(fn ($q) => $q->whereNull('ta.ends_on')->orWhere('ta.ends_on', '>=', $date)))
                ->when($v['to'] ?? null, fn ($q, $date) => $q->where(fn ($q) => $q->whereNull('ta.starts_on')->orWhere('ta.starts_on', '<=', $date)));
            $rows = $q->orderBy('u.name')->limit(5001)->get(['u.account_code', 'p.employee_number', 'u.name', 'y.name as session', 'c.name as class', 'sec.name as section', 'sub.name as subject', 'ta.is_class_teacher', 'ta.starts_on', 'ta.ends_on', 'ta.is_active']);
        } elseif ($type === 'class_attendance') {
            $q = DB::table('attendance_records as a')->join('attendance_registers as reg', 'reg.id', '=', 'a.attendance_register_id')->join('students as s', 's.id', '=', 'a.student_id')->join('sections as sec', 'sec.id', '=', 'reg.section_id')->join('classes as c', 'c.id', '=', 'sec.class_id')->where('reg.status', 'submitted')->whereIn('a.student_id', $students->select('students.id'));
            $filters->records($q, $r, 'reg.section_id', 'a.date', $v);
            $access->academicRecords($q, $r->user(), 'reg.section_id', null, 'a.date');
            $q->when($v['status'] ?? null, fn ($q, $status) => $q->where('a.status', $status));
            $rows = $q->orderBy('a.date')->orderBy('s.registration_number')->limit(5001)->get(['a.date', 'c.name as class', 'sec.name as section', 's.registration_number', 's.full_name', 'a.status']);
        } elseif ($type === 'class_results') {
            $q = DB::table('assessment_results as result')->join('assessments as a', 'a.id', '=', 'result.assessment_id')->join('students as s', 's.id', '=', 'result.student_id')->join('subjects as sub', 'sub.id', '=', 'a.subject_id')->where('a.status', 'published')->whereIn('result.student_id', $students->select('students.id'));
            $filters->records($q, $r, 'a.section_id', 'a.date', $v);
            $access->academicRecords($q, $r->user(), 'a.section_id', 'a.subject_id', 'a.date');
            $q->when($v['subject_id'] ?? null, fn ($q, $id) => $q->where('a.subject_id', $id));
            $rows = $q->orderBy('a.date')->orderBy('s.registration_number')->limit(5001)->get(['s.registration_number', 's.full_name', 'a.title', 'a.date', 'sub.name as subject', 'result.maximum_marks', 'result.obtained_marks', 'result.percentage', 'result.grade']);
        } elseif ($type === 'payment_register') {
            $access->require($r->user(), 'finance.read');
            $attributed = DB::table('payment_allocations as a')->join('fee_invoices as i', 'i.id', '=', 'a.fee_invoice_id')->whereIn('i.student_id', (clone $students)->select('students.id'))->groupBy('a.payment_id')->selectRaw('a.payment_id, sum(a.amount_minor) as amount');
            $refunds = DB::table('refund_allocations as r')->join('payment_allocations as a', 'a.id', '=', 'r.payment_allocation_id')->join('fee_invoices as i', 'i.id', '=', 'a.fee_invoice_id')->whereIn('i.student_id', (clone $students)->select('students.id'))->groupBy('a.payment_id')->selectRaw('a.payment_id, sum(r.amount_minor) as amount');
            $q = DB::table('payments as p')->join('payment_methods as m', 'm.id', '=', 'p.payment_method_id')->joinSub($attributed, 'attributed', fn ($j) => $j->on('attributed.payment_id', '=', 'p.id'))->leftJoinSub($refunds, 'refunds', fn ($j) => $j->on('refunds.payment_id', '=', 'p.id'))
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('payment_allocations as a')->join('fee_invoices as i', 'i.id', '=', 'a.fee_invoice_id')->whereColumn('a.payment_id', 'p.id')->whereNotIn('i.student_id', $access->students($r->user())->select('students.id')));
            $q->when($v['status'] ?? null, fn ($q, $status) => $q->where('p.status', $status))->when($v['from'] ?? null, fn ($q, $date) => $q->where('p.paid_on', '>=', $date))->when($v['to'] ?? null, fn ($q, $date) => $q->where('p.paid_on', '<=', $date));
            $rows = $q->orderBy('p.paid_on')->limit(5001)->select(['p.receipt_number', 'p.paid_on', 'p.amount_minor as receipt_amount_minor', 'attributed.amount as amount_minor', 'p.status', 'm.name as method', 'p.reference'])->selectRaw("coalesce(refunds.amount,0) as refunded_minor, case when p.status='verified' then attributed.amount-coalesce(refunds.amount,0) else 0 end as collected_minor")->get();
        } else {
            $access->require($r->user(), 'payroll.read');
            $paid = DB::table('payroll_payments')->groupBy('payroll_period_id')->selectRaw('payroll_period_id, sum(amount_minor) as amount');
            $rows = DB::table('payroll_periods as p')->leftJoinSub($paid, 'paid', fn ($j) => $j->on('paid.payroll_period_id', '=', 'p.id'))
                ->when($v['period'] ?? null, fn ($q, $period) => $q->where('p.period', $period))->orderBy('p.period')->limit(5001)
                ->selectRaw("p.period, p.status, p.total_minor, case when p.status='paid' and paid.amount is null then p.total_minor else coalesce(paid.amount,0) end as paid_minor")
                ->get()->map(fn ($p) => ['payroll_number' => 'INF-SG-PR-'.$p->period, ...(array) $p, 'balance_minor' => (int) $p->total_minor - (int) $p->paid_minor]);
        }

        return $rows->map(fn ($row) => (array) $row)->all();
    }
}
