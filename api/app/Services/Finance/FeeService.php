<?php

namespace App\Services\Finance;

use App\Models\User;
use App\Services\NotificationService;
use App\Support\Audit;
use App\Support\Domain;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class FeeService
{
    public function __construct(private JournalService $journal, private InvoiceBalance $balance) {}

    public function configuredAmount(string $student, string $session, string $class, string $kind, string $start, string $end): ?int
    {
        $assignment = DB::table('student_fee_assignments')->where('student_id', $student)->where('academic_session_id', $session)->where('effective_on', '<=', $start)->orderByDesc('effective_on')->first();
        if ($kind === 'tuition' && $assignment?->tuition_override_minor !== null) {
            return (int) $assignment->tuition_override_minor;
        }
        $schedule = DB::table('fee_schedules')->where('academic_session_id', $session)->where('class_id', $class)->where('kind', $kind)->where('effective_on', '<=', $start)->orderByDesc('effective_on')->first();

        return $schedule ? (int) $schedule->amount_minor : null;
    }

    public function tuitionQuote(string $student, string $session, string $period): ?array
    {
        $start = $period.'-01';
        $end = Carbon::parse($start)->endOfMonth()->toDateString();
        $enrollment = DB::table('enrollments as e')->join('sections as s', 's.id', '=', 'e.section_id')->where('e.student_id', $student)->where('e.academic_session_id', $session)->where('e.starts_on', '<=', $end)->where(fn ($q) => $q->whereNull('e.ends_on')->orWhere('e.ends_on', '>=', $start))->orderByDesc('e.starts_on')->first(['s.class_id', 'e.starts_on']);
        if (! $enrollment) {
            return null;
        }
        $sessionRow = DB::table('academic_sessions')->find($session);
        $identity = DB::table('students')->find($student);
        $start = max($start, $enrollment->starts_on, $sessionRow->starts_on, $identity->admitted_on);
        $gross = $this->configuredAmount($student, $session, $enrollment->class_id, 'tuition', $start, $end);
        if ($gross === null) {
            return null;
        }
        $discount = $this->discount($student, $session, $start, $gross, $gross);

        return ['gross_minor' => $gross, 'discount_minor' => $discount, 'net_minor' => $gross - $discount];
    }

    private function discount(string $student, string $session, string $start, int $gross, int $discountBase): int
    {
        $discount = 0;
        $policies = DB::table('student_discounts as d')->join('discount_policies as p', 'p.id', '=', 'd.discount_policy_id')->where('d.student_id', $student)->where('d.academic_session_id', $session)->where(fn ($q) => $q->where('d.status', 'approved')->orWhere(fn ($q) => $q->where('d.status', 'revoked')->whereNotNull('d.approved_by')->whereNotNull('d.approved_at')))->where(fn ($q) => $q->whereNull('d.revoked_at')->orWhereDate('d.revoked_at', '>', $start))->where('d.starts_on', '<=', $start)->where('d.ends_on', '>=', $start)->where('p.is_active', true)->get(['d.basis_points', 'p.maximum_basis_points', 'p.applies_to']);
        // Highest applicable monetary discount wins. Stacking is deliberately not enabled.
        foreach ($policies as $policy) {
            if ($policy->basis_points > $policy->maximum_basis_points) {
                continue;
            }$base = $policy->applies_to === 'all' ? $gross : $discountBase;
            $discount = max($discount, intdiv($base * (int) $policy->basis_points + 5000, 10000));
        }

        return $discount;
    }

    public function generate(User $user, array $v): string
    {
        return DB::transaction(function () use ($user, $v) {
            app(FinancialLock::class)->request('invoice', $v['request_id']);
            $hash = hash('sha256', json_encode($v));
            $old = DB::table('fee_invoices')->where('request_id', $v['request_id'])->first();
            if ($old) {
                abort_unless(hash_equals($old->payload_hash, $hash), 409);

                return $old->id;
            }
            $student = DB::table('students')->where('id', $v['student_id'])->lockForUpdate()->firstOrFail();
            $date = Carbon::parse($v['period'].'-01');
            $end = $date->copy()->endOfMonth()->toDateString();
            $start = $date->toDateString();
            $session = DB::table('academic_sessions')->where('id', $v['academic_session_id'])->firstOrFail();
            if ($end < $session->starts_on || $start > $session->ends_on) {
                Domain::fail('period', 'Fee period is outside the academic session.');
            }
            $enrollment = DB::table('enrollments as e')->join('sections as s', 's.id', '=', 'e.section_id')->where('e.student_id', $student->id)->where('e.academic_session_id', $session->id)->where('e.starts_on', '<=', $end)->where(fn ($q) => $q->whereNull('e.ends_on')->orWhere('e.ends_on', '>=', $start))->orderByDesc('e.starts_on')->first(['e.*', 's.class_id']);
            if (! $enrollment && $v['kind'] !== 'opening_balance') {
                Domain::fail('student_id', 'The student has no enrollment in this fee period.');
            }
            if ($v['kind'] === 'tuition' && $end < $student->admitted_on) {
                Domain::fail('period', 'Tuition cannot precede admission.');
            }
            if ($v['kind'] === 'tuition' && DB::table('opening_balance_coverage as c')->join('fee_invoices as f', 'f.id', '=', 'c.fee_invoice_id')->where('f.student_id', $student->id)->where('c.from_period', '<=', $v['period'])->where('c.through_period', '>=', $v['period'])->exists()) {
                Domain::fail('period', 'This month is included in an approved opening balance.');
            }
            $duplicate = DB::table('fee_invoices')->where('student_id', $student->id)->where('period', $v['period'])->where('kind', $v['kind']);
            if ($v['kind'] !== 'tuition') {
                $duplicate->where('academic_session_id', $session->id);
            }
            if ($duplicate->exists()) {
                abort(409);
            }
            $lines = [];
            $discountBase = 0;
            if (in_array($v['kind'], ['tuition', 'admission', 'promotion'])) {
                $kind = $v['kind'] === 'tuition' ? 'tuition' : 'admission_promotion';
                $start = max($start, $enrollment->starts_on, $session->starts_on, $student->admitted_on);
                $configured = $this->configuredAmount($student->id, $session->id, $enrollment->class_id, $kind, $start, $end);
                if ($configured === null) {
                    Domain::fail('fee_schedule', 'Configure a fee schedule for this class and session.');
                }
                $amount = $configured;
                if ($v['kind'] === 'promotion' && ! DB::table('student_transitions as t')->join('enrollments as e', 'e.id', '=', 't.to_enrollment_id')->where('t.student_id', $student->id)->where('t.type', 'promotion')->where('e.academic_session_id', $session->id)->whereBetween('t.effective_on', [$start, $end])->exists()) {
                    Domain::fail('kind', 'A matching promotion transition is required.');
                }
                $lines[] = ['description' => $v['kind'] === 'tuition' ? 'Monthly tuition' : 'Admission / promotion fee', 'kind' => $v['kind'], 'amount_minor' => $amount];
                if ($kind === 'tuition') {
                    $discountBase = $amount;
                }
            }
            foreach ($v['charges'] ?? [] as $line) {
                $lines[] = ['description' => $line['description'], 'kind' => 'additional', 'amount_minor' => $line['amount_minor']];
            }
            if (! $lines) {
                Domain::fail('charges', 'At least one charge is required.');
            }$gross = array_sum(array_column($lines, 'amount_minor'));
            $discount = $this->discount($student->id, $session->id, $start, $gross, $discountBase);
            $id = Domain::insert('fee_invoices', ['voucher_number' => app(DocumentNumber::class)->next('FV', $v['issued_on']), 'student_id' => $student->id, 'academic_session_id' => $session->id, 'period' => $v['period'], 'kind' => $v['kind'], 'issued_on' => $v['issued_on'], 'due_on' => $v['due_on'], 'gross_minor' => $gross, 'discount_minor' => $discount, 'total_minor' => $gross - $discount, 'request_id' => $v['request_id'], 'payload_hash' => $hash, 'created_by' => $user->id]);
            foreach ($lines as $line) {
                Domain::insert('invoice_lines', ['fee_invoice_id' => $id, ...$line]);
            }
            if ($gross > 0) {
                $this->journal->post($user, 'invoice:'.$id, $v['issued_on'], 'Fee voucher', 'fee_invoice', $id, [['account_id' => $this->journal->account('receivables'), 'debit_minor' => $gross - $discount], ['account_id' => $this->journal->account('discount_expense'), 'debit_minor' => $discount], ['account_id' => $this->journal->account($v['kind'] === 'opening_balance' ? 'opening_equity' : 'tuition_revenue'), 'credit_minor' => $gross]]);
            }
            Audit::record('fee_invoice_finalized', $user->id, 'fee_invoices', $id);
            app(NotificationService::class)->parents($student->id, 'fee_voucher', 'invoice', $id, 'invoice:'.$id);

            return $id;
        });
    }

    public function adjust(User $user, string $invoice, array $v): string
    {
        return DB::transaction(function () use ($user, $invoice, $v) {
            app(FinancialLock::class)->request('adjustment', $v['request_id']);
            $old = DB::table('invoice_adjustments')->where('request_id', $v['request_id'])->first();
            if ($old) {
                abort_unless($old->fee_invoice_id === $invoice && (int) $old->amount_minor === $v['amount_minor'] && $old->reason === $v['reason'], 409);

                return $old->id;
            }
            $row = DB::table('fee_invoices')->where('id', $invoice)->lockForUpdate()->firstOrFail();
            $balance = $this->balance->calculate($row);
            if ($v['amount_minor'] === 0 || $balance['balance_minor'] + $v['amount_minor'] < 0) {
                Domain::fail('amount_minor', 'Adjustment cannot create an unallocated overpayment; refund first.');
            }
            $id = Domain::insert('invoice_adjustments', ['fee_invoice_id' => $invoice, 'amount_minor' => $v['amount_minor'], 'reason' => $v['reason'], 'request_id' => $v['request_id'], 'approved_by' => $user->id]);
            $amount = abs($v['amount_minor']);
            $lines = $v['amount_minor'] < 0 ? [['account_id' => $this->journal->account('adjustment_expense'), 'debit_minor' => $amount], ['account_id' => $this->journal->account('receivables'), 'credit_minor' => $amount]] : [['account_id' => $this->journal->account('receivables'), 'debit_minor' => $amount], ['account_id' => $this->journal->account('tuition_revenue'), 'credit_minor' => $amount]];
            $this->journal->post($user, 'adjustment:'.$id, now()->toDateString(), 'Approved fee adjustment', 'invoice_adjustment', $id, $lines);
            Audit::record('invoice_adjustment_approved', $user->id, 'invoice_adjustments', $id);

            return $id;
        });
    }
}
