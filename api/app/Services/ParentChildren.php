<?php

namespace App\Services;

use App\Models\User;
use App\Services\Finance\InvoiceBalance;
use Illuminate\Support\Facades\DB;

final class ParentChildren
{
    public function get(User $user, ?string $student = null)
    {
        $access = app(SchoolAccess::class);
        abort_unless($access->isParent($user), 403);
        if ($student) {
            $access->student($user, $student);
        }
        $children = $access->students($user)->when($student, fn ($q) => $q->where('students.id', $student))->orderBy('full_name')->get(['id', 'full_name', 'registration_number', 'photo_file_id', 'status']);
        $ids = $children->pluck('id')->all();
        $today = now()->toDateString();
        $enrollments = DB::table('enrollments as e')->join('sections as s', 's.id', '=', 'e.section_id')->join('classes as c', 'c.id', '=', 's.class_id')->join('academic_sessions as a', 'a.id', '=', 'e.academic_session_id')->whereIn('e.student_id', $ids)->where('e.starts_on', '<=', $today)->where(fn ($q) => $q->whereNull('e.ends_on')->orWhere('e.ends_on', '>=', $today))->orderByDesc('e.starts_on')->get(['e.student_id', 'e.roll_number', 's.name as section_name', 'c.name as class_name', 'a.name as session_name'])->groupBy('student_id');
        $attendance = app(AcademicSummary::class)->attendanceMany($ids, now()->format('Y-m'));
        $invoices = DB::table('fee_invoices')->whereIn('student_id', $ids)->get();
        $balances = app(InvoiceBalance::class)->many($invoices);
        $invoices = $invoices->groupBy('student_id');
        $latest = DB::table('assessment_results as r')->join('assessments as a', 'a.id', '=', 'r.assessment_id')->join('subjects as s', 's.id', '=', 'a.subject_id')->whereIn('r.student_id', $ids)->where('a.status', 'published')->selectRaw('r.student_id,a.title,a.date,s.name as subject_name,r.percentage,r.grade')->orderBy('r.student_id')->orderByDesc('a.date')->orderByDesc('a.published_at')->orderByDesc('a.id')->get()->unique('student_id')->keyBy('student_id');

        return $children->map(function ($child) use ($enrollments, $attendance, $invoices, $balances, $latest) {
            $a = $attendance[$child->id];
            $own = $invoices->get($child->id, collect());
            $balance = $own->sum(fn ($i) => $balances[$i->id]['balance_minor']);

            return [...(array) $child, 'enrollment' => $enrollments->get($child->id, collect())->first(), 'attendance_percentage' => $a['attendance_percentage'], 'attendance_provisional' => $a['provisional'], 'recorded_days' => $a['recorded_days'], 'balance_minor' => $balance, 'fee_status' => $own->isEmpty() ? 'no_vouchers' : ($balance > 0 ? 'balance_due' : 'no_balance'), 'latest_academic' => $latest->get($child->id)];
        });
    }
}
