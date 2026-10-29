<?php

namespace App\Services\Finance;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Request-local read facts. Never used to authorize or post financial writes. */
final class BillingSnapshot
{
    public readonly Collection $students;

    public readonly Collection $enrollments;

    public readonly Collection $invoices;

    public readonly Collection $coverage;

    public readonly Collection $sessions;

    public readonly Collection $schedules;

    public readonly Collection $assignments;

    public readonly Collection $discounts;

    public readonly array $balances;

    public function __construct(array $students)
    {
        $this->students = DB::table('students')->whereIn('id', $students)->get()->keyBy('id');
        $enrollments = DB::table('enrollments as e')->join('sections as s', 's.id', '=', 'e.section_id')->join('academic_sessions as a', 'a.id', '=', 'e.academic_session_id')
            ->whereIn('e.student_id', $students)->orderByDesc('e.starts_on')->get(['e.*', 's.class_id', 'a.starts_on as session_start', 'a.ends_on as session_end', 'a.name as session_name']);
        $this->enrollments = $enrollments->groupBy('student_id');
        $invoices = DB::table('fee_invoices')->whereIn('student_id', $students)->orderBy('created_at')->get();
        $this->invoices = $invoices->groupBy('student_id');
        $this->balances = app(InvoiceBalance::class)->many($invoices->where('kind', 'tuition'));
        $this->coverage = DB::table('opening_balance_coverage as c')->join('fee_invoices as i', 'i.id', '=', 'c.fee_invoice_id')->whereIn('i.student_id', $students)->get(['c.*', 'i.voucher_number', 'i.student_id'])->groupBy('student_id');
        $sessions = $enrollments->pluck('academic_session_id')->merge($invoices->pluck('academic_session_id'))->unique();
        $this->sessions = DB::table('academic_sessions')->whereIn('id', $sessions)->get()->keyBy('id');
        $this->schedules = DB::table('fee_schedules')->whereIn('academic_session_id', $sessions)->whereIn('class_id', $enrollments->pluck('class_id')->unique())->orderByDesc('effective_on')->get();
        $this->assignments = DB::table('student_fee_assignments')->whereIn('student_id', $students)->orderByDesc('effective_on')->get()->groupBy('student_id');
        $this->discounts = DB::table('student_discounts as d')->join('discount_policies as p', 'p.id', '=', 'd.discount_policy_id')->whereIn('d.student_id', $students)->where('p.is_active', true)
            ->where(fn ($q) => $q->where('d.status', 'approved')->orWhere(fn ($q) => $q->where('d.status', 'revoked')->whereNotNull('d.approved_by')->whereNotNull('d.approved_at')))
            ->get(['d.*', 'p.maximum_basis_points', 'p.applies_to'])->groupBy('student_id');
    }

    public function enrollmentsFor(string $student): Collection
    {
        return $this->enrollments->get($student, collect());
    }

    public function invoicesFor(string $student): Collection
    {
        return $this->invoices->get($student, collect());
    }

    public function coverageFor(string $student): Collection
    {
        return $this->coverage->get($student, collect());
    }
}
