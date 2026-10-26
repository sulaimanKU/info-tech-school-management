<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AnnouncementAccess;
use App\Services\SchoolAccess;
use App\Services\TicketAccess;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DashboardController extends Controller
{
    public function __construct(private SchoolAccess $access) {}

    public function show(Request $r)
    {
        $u = $r->user();
        abort_if($this->access->isParent($u), 403);
        $r->validate(array_fill_keys(['user_id', 'student_id', 'section_id', 'class_id', 'academic_session_id'], 'prohibited'));
        $today = now()->toDateString();
        $academic = $u->hasPermission('academics.read') || $u->hasPermission('academics.write') || $u->hasPermission('academics.review');
        $canStudents = $u->hasPermission('students.read') || $u->hasPermission('students.read.all') || $u->hasPermission('students.manage') || $u->hasPermission('academics.write');
        $students = $canStudents ? $this->access->students($u) : DB::table('students')->whereRaw('1=0');
        $sections = $this->access->sections($u);
        $data = ['as_of' => now()->toIso8601String(), 'scope' => $u->hasPermission('students.read.all') ? 'school' : 'assigned', 'unread_notifications' => DB::table('school_notifications')->where('user_id', $u->id)->whereNull('read_at')->where(fn ($q) => $q->whereNull('student_id')->orWhereIn('student_id', (clone $students)->select('students.id')))->count()];
        if ($canStudents) {
            $counts = (clone $students)->selectRaw("count(*) as total, count(*) filter (where status='active') as active")->first();
            $data['students'] = ['total' => (int) $counts->total, 'active' => (int) $counts->active];
        }
        if ($u->hasPermission('staff.manage')) {
            $data['staff'] = ['active' => User::where('is_active', true)->whereHas('roles', fn ($q) => $q->where('code', '!=', 'parent_guardian'))->count(), 'teachers' => User::where('is_active', true)->whereHas('roles', fn ($q) => $q->where('code', 'teacher'))->count()];
        }
        if ($u->hasPermission('guardians.manage')) {
            $data['guardians'] = DB::table('parent_guardians as g')->join('users as p', 'p.id', '=', 'g.user_id')->where('p.is_active', true)->whereNotNull('g.mobile_verified_at')->whereExists(fn ($q) => $q->selectRaw('1')->from('parent_student_links as l')->whereColumn('l.parent_guardian_id', 'g.id')->where('l.is_active', true)->whereNotNull('l.verified_at')->whereIn('l.student_id', (clone $students)->select('students.id')))->count();
        }
        if ($academic || $u->hasPermission('school.manage')) {
            $data['school'] = ['classes' => $u->hasPermission('school.manage') ? DB::table('classes')->count() : DB::query()->fromSub((clone $sections)->select('class_id')->distinct(), 'assigned_classes')->count(), 'sections' => (clone $sections)->count(), 'subjects' => $academic ? $this->access->subjects($u)->count() : null, 'sessions' => DB::table('academic_sessions')->where('starts_on', '<=', $today)->where('ends_on', '>=', $today)->whereIn('id', (clone $sections)->select('academic_session_id'))->orderByDesc('starts_on')->limit(5)->get(['name'])];
        }
        if ($academic) {
            $data['attendance'] = $this->attendance($students, $sections, $today);
            $diary = app(DiaryController::class)->visible($r);
            $assessments = DB::table('assessments')->whereIn('section_id', (clone $sections)->select('sections.id'));
            $this->access->academicRecords($assessments, $u, 'assessments.section_id', 'assessments.subject_id', 'assessments.date');
            if (! $u->hasPermission('academics.review')) {
                $assessments->where('teacher_user_id', $u->id);
            }
            $data['academic'] = ['homework_today' => (clone $diary)->where('d.status', 'published')->where('d.date', $today)->count(), 'pending_assessments' => $assessments->where('status', $u->hasPermission('academics.review') ? 'submitted' : 'draft')->count(), 'assessment_state' => $u->hasPermission('academics.review') ? 'submitted' : 'draft'];
        }
        if ($u->hasPermission('finance.read') && $canStudents) {
            $data['finance'] = $this->finance($students, $today);
        }
        if ($canStudents && $u->hasPermission('communication.manage')) {
            $data['open_requests'] = app(TicketAccess::class)->query($u)->whereNotIn('t.status', ['resolved', 'closed'])->count();
        }
        $data['announcements'] = app(AnnouncementAccess::class)->query($u)->where('a.status', 'published')->where('a.publish_at', '<=', now())->where(fn ($q) => $q->whereNull('a.expires_at')->orWhere('a.expires_at', '>', now()))->orderByDesc('a.publish_at')->limit(3)->get(['a.id', 'a.title', 'a.kind', 'a.publish_at']);

        return Domain::response($data)->header('Cache-Control', 'private, no-store');
    }

    private function attendance($students, $sections, string $today): array
    {
        $weekdays = json_decode(DB::table('school_settings')->where('key', 'school_weekdays')->value('value') ?? '[1,2,3,4,5,6]', true);
        $normalDay = in_array(now()->dayOfWeekIso, $weekdays, true);
        $roster = DB::table('enrollments as e')->join('academic_sessions as current_session', 'current_session.id', '=', 'e.academic_session_id')->where('current_session.starts_on', '<=', $today)->where('current_session.ends_on', '>=', $today)->where('e.starts_on', '<=', $today)->where(fn ($q) => $q->whereNull('e.ends_on')->orWhere('e.ends_on', '>=', $today))->whereIn('e.student_id', (clone $students)->select('students.id'))->whereIn('e.section_id', (clone $sections)->select('sections.id'))->where(function ($q) use ($today, $normalDay) {
            $override = fn ($q) => $q->selectRaw('1')->from('school_days as sd')->whereColumn('sd.academic_session_id', 'e.academic_session_id')->where('sd.date', $today)->where('sd.is_school_day', ! $normalDay);
            if ($normalDay) {
                $q->whereNotExists($override);
            } else {
                $q->whereExists($override);
            }
        });
        $expected = (clone $roster)->distinct()->count('e.student_id');
        $rows = DB::table('attendance_records as a')->join('attendance_registers as r', 'r.id', '=', 'a.attendance_register_id')->where('r.status', 'submitted')->where('a.date', $today)->whereIn('a.student_id', (clone $roster)->select('e.student_id'))->whereIn('r.section_id', (clone $sections)->select('sections.id'))->where('a.status', '!=', 'school_holiday');
        $group = (clone $rows)->selectRaw('a.status,count(distinct a.student_id) as count')->groupBy('a.status')->pluck('count', 'status');
        $recorded = (clone $rows)->distinct()->count('a.student_id');

        return ['date' => $today, 'expected' => $expected, 'recorded' => $recorded, 'present' => (int) ($group['present'] ?? 0), 'absent' => (int) ($group['absent'] ?? 0), 'late' => (int) ($group['late'] ?? 0), 'excused' => (int) ($group['excused'] ?? 0), 'not_marked' => max(0, $expected - $recorded), 'coverage_percentage' => $expected ? round(100 * $recorded / $expected, 1) : null];
    }

    private function finance($students, string $today): array
    {
        $paid = DB::table('payment_allocations as a')->join('payments as p', 'p.id', '=', 'a.payment_id')->where('p.status', 'verified')->groupBy('a.fee_invoice_id')->selectRaw('a.fee_invoice_id,sum(a.amount_minor) as amount');
        $refunds = DB::table('refund_allocations as r')->join('payment_allocations as a', 'a.id', '=', 'r.payment_allocation_id')->groupBy('a.fee_invoice_id')->selectRaw('a.fee_invoice_id,sum(r.amount_minor) as amount');
        $adjustments = DB::table('invoice_adjustments')->groupBy('fee_invoice_id')->selectRaw('fee_invoice_id,sum(amount_minor) as amount');
        $balances = DB::table('fee_invoices as i')->leftJoinSub($paid, 'paid', fn ($j) => $j->on('paid.fee_invoice_id', '=', 'i.id'))->leftJoinSub($refunds, 'refunds', fn ($j) => $j->on('refunds.fee_invoice_id', '=', 'i.id'))->leftJoinSub($adjustments, 'adjustments', fn ($j) => $j->on('adjustments.fee_invoice_id', '=', 'i.id'))->whereIn('i.student_id', (clone $students)->select('students.id'))->selectRaw('i.student_id,i.due_on,greatest(i.total_minor+coalesce(adjustments.amount,0)-coalesce(paid.amount,0)+coalesce(refunds.amount,0),0) as balance_minor');
        $totals = DB::query()->fromSub($balances, 'balances')->selectRaw('coalesce(sum(balance_minor),0) as outstanding,coalesce(sum(case when due_on < ? then balance_minor else 0 end),0) as overdue,count(distinct case when balance_minor > 0 then student_id end) as debtors', [$today])->first();
        $receipts = DB::table('payment_allocations as a')->join('payments as p', 'p.id', '=', 'a.payment_id')->join('fee_invoices as i', 'i.id', '=', 'a.fee_invoice_id')->where('p.status', 'verified')->whereBetween('p.paid_on', [now()->startOfMonth()->toDateString(), $today])->whereIn('i.student_id', (clone $students)->select('students.id'))->sum('a.amount_minor');

        return ['outstanding_minor' => (int) $totals->outstanding, 'overdue_minor' => (int) $totals->overdue, 'students_with_balance' => (int) $totals->debtors, 'receipts_month_minor' => (int) $receipts, 'period' => now()->format('Y-m')];
    }
}
