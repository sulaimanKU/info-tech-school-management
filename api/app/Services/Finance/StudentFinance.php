<?php

namespace App\Services\Finance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Read model over existing immutable vouchers, allocations and fee configuration. */
final class StudentFinance
{
    public function snapshot(string $student, ?string $session, string $period): array
    {
        $filterSession = $session;
        $enrollment = DB::table('enrollments as e')->join('sections as s', 's.id', '=', 'e.section_id')->join('classes as c', 'c.id', '=', 's.class_id')->join('academic_sessions as a', 'a.id', '=', 'e.academic_session_id')->where('e.student_id', $student)->where('e.starts_on', '<=', Carbon::parse($period.'-01')->endOfMonth()->toDateString())->where(fn ($q) => $q->whereNull('e.ends_on')->orWhere('e.ends_on', '>=', $period.'-01'))->when($session, fn ($q) => $q->where('e.academic_session_id', $session))->orderByDesc('e.starts_on')->first(['c.name as class_name', 's.name as section_name', 'e.academic_session_id', 's.class_id', 'e.starts_on', 'a.name as session_name']);
        $enrollment ??= DB::table('enrollments as e')->join('sections as s', 's.id', '=', 'e.section_id')->join('classes as c', 'c.id', '=', 's.class_id')->join('academic_sessions as a', 'a.id', '=', 'e.academic_session_id')->where('e.student_id', $student)->where('e.starts_on', '<=', Carbon::parse($period.'-01')->endOfMonth()->toDateString())->when($session, fn ($q) => $q->where('e.academic_session_id', $session))->orderByDesc('e.starts_on')->first(['c.name as class_name', 's.name as section_name', 'e.academic_session_id', 's.class_id', 'e.starts_on', 'a.name as session_name']);
        $session ??= $enrollment?->academic_session_id;
        $invoices = DB::table('fee_invoices')->where('student_id', $student)->when($filterSession, fn ($q) => $q->where('academic_session_id', $filterSession))->orderByDesc('period')->get();
        $balances = app(InvoiceBalance::class)->many($invoices);
        $items = $invoices->map(fn ($i) => [...array_intersect_key((array) $i, array_flip(['id', 'voucher_number', 'student_id', 'academic_session_id', 'period', 'kind', 'issued_on', 'due_on', 'gross_minor', 'discount_minor', 'total_minor'])), ...$balances[$i->id]]);
        $quote = $session ? app(FeeService::class)->tuitionQuote($student, $session, $period) : null;
        $unbilled = $quote && ! $items->contains(fn ($i) => $i['kind'] === 'tuition' && $i['period'] === $period);
        $schedules = $session ? DB::table('fee_schedules as f')->where('f.academic_session_id', $session)->where('f.class_id', $enrollment?->class_id)->where('effective_on', '<=', Carbon::parse($period.'-01')->endOfMonth()->toDateString())->orderByDesc('effective_on')->get(['f.kind', 'f.amount_minor', 'f.effective_on'])->unique('kind')->values() : collect();

        $monthly = app(MonthlyBilling::class)->plan($student, $period, $filterSession);
        $missing = collect($monthly)->whereNull('invoice_id');

        return [
            'opening_balances_needing_review' => app(MonthlyBilling::class)->unreviewedOpenings($student), 'monthly_periods' => $monthly, 'missing_months' => $missing->count(), 'months_needing_configuration' => $missing->where('status', 'needs_configuration')->count(),
            'admission_charges_minor' => (int) $items->whereIn('kind', ['admission', 'additional', 'opening_balance'])->sum('net_total_minor'),
            'class' => $enrollment ? (array) $enrollment : [], 'academic_session_id' => $session, 'period' => $period,
            'filter_session_id' => $filterSession, 'billing_basis' => 'posted_vouchers', 'voucher_required' => (bool) $unbilled,
            'configured_fee_minor' => $quote['gross_minor'] ?? 0, 'configured_discount_minor' => $quote['discount_minor'] ?? 0,
            'configured_net_minor' => $quote['net_minor'] ?? 0, 'unbilled_minor' => (int) $missing->sum('net_minor'),
            'total_minor' => (int) $items->sum('net_total_minor'), 'gross_minor' => (int) $items->sum('gross_minor'),
            'discount_minor' => (int) $items->sum('discount_minor'), 'paid_minor' => (int) $items->sum('paid_minor'),
            'balance_minor' => (int) $items->sum('balance_minor'), 'credit_minor' => max(0, -(int) $items->sum('balance_minor')),
            'current_period_fee_minor' => (int) $items->where('period', $period)->sum('net_total_minor'),
            'current_period_due_minor' => (int) $items->where('period', $period)->sum('balance_minor'),
            'arrears_minor' => (int) $items->filter(fn ($i) => $i['period'] < $period)->sum('balance_minor'),
            'items' => $items, 'fee_schedule' => $schedules,
        ];
    }
}
