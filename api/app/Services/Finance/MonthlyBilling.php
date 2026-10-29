<?php

namespace App\Services\Finance;

use App\Models\User;
use App\Support\Audit;
use App\Support\Domain;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MonthlyBilling
{
    public function coverOpening(User $actor, string $invoice, string $from, string $through): string
    {
        abort_unless($actor->hasPermission('finance.approve'), 403);

        return DB::transaction(function () use ($actor, $invoice, $from, $through) {
            $i = DB::table('fee_invoices')->find($invoice);
            abort_unless($i && $i->kind === 'opening_balance', 422);
            DB::table('students')->where('id', $i->student_id)->lockForUpdate()->firstOrFail();
            $old = DB::table('opening_balance_coverage')->where('fee_invoice_id', $invoice)->first();
            if ($old) {
                abort_unless($old->from_period === $from && $old->through_period === $through, 409);

                return $old->id;
            }
            if ($from > $through || $through > now()->format('Y-m')) {
                Domain::fail('covered_through', 'Choose a valid historical coverage range.');
            }
            if (DB::table('fee_invoices')->where('student_id', $i->student_id)->where('kind', 'tuition')->whereBetween('period', [$from, $through])->exists()
                || DB::table('opening_balance_coverage as c')->join('fee_invoices as f', 'f.id', '=', 'c.fee_invoice_id')->where('f.student_id', $i->student_id)->where('c.from_period', '<=', $through)->where('c.through_period', '>=', $from)->exists()) {
                Domain::fail('covered_from', 'Coverage overlaps an existing tuition voucher or another opening balance. Reconcile the existing charges first.');
            }
            $id = Domain::insert('opening_balance_coverage', ['fee_invoice_id' => $invoice, 'from_period' => $from, 'through_period' => $through, 'approved_by' => $actor->id]);
            Audit::record('opening_coverage_approved', $actor->id, 'fee_invoices', $invoice);

            return $id;
        });
    }

    public function plan(string $student, string $through, ?string $session = null, bool $currentOnly = false, ?string $class = null, ?string $section = null, ?BillingSnapshot $snapshot = null): array
    {
        $identity = $snapshot ? $snapshot->students->get($student) : DB::table('students')->find($student);
        abort_unless($identity, 404);
        $cutoff = min($through, now()->format('Y-m'));
        if ($currentOnly && $through !== $cutoff) {
            return [];
        }
        $enrollments = $snapshot ? $snapshot->enrollmentsFor($student) : DB::table('enrollments as e')->join('sections as s', 's.id', '=', 'e.section_id')->join('academic_sessions as a', 'a.id', '=', 'e.academic_session_id')
            ->where('e.student_id', $student)->orderByDesc('e.starts_on')->get(['e.*', 's.class_id', 'a.starts_on as session_start', 'a.ends_on as session_end', 'a.name as session_name']);
        if ($enrollments->isEmpty()) {
            return [];
        }
        $first = max($identity->admitted_on, $enrollments->min('starts_on'));
        $month = Carbon::parse($currentOnly ? $cutoff.'-01' : substr($first, 0, 7).'-01');
        $posted = $snapshot ? $snapshot->invoicesFor($student)->where('kind', 'tuition')->keyBy('period') : DB::table('fee_invoices')->where('student_id', $student)->where('kind', 'tuition')->orderBy('created_at')->get()->keyBy('period');
        $balances = $snapshot ? $snapshot->balances : app(InvoiceBalance::class)->many($posted);
        $coverage = $snapshot ? $snapshot->coverageFor($student) : DB::table('opening_balance_coverage as c')->join('fee_invoices as i', 'i.id', '=', 'c.fee_invoice_id')->where('i.student_id', $student)->get(['c.*', 'i.voucher_number']);
        $rows = [];
        for (; $month->format('Y-m') <= $cutoff; $month->addMonth()) {
            $period = $month->format('Y-m');
            $start = $month->toDateString();
            $end = $month->copy()->endOfMonth()->toDateString();
            // One monthly charge: the latest applicable class/enrollment in a transition month wins.
            $enrollment = $enrollments->first(fn ($e) => max($e->starts_on, $e->session_start, $identity->admitted_on) <= $end && min($e->ends_on ?? $end, $e->session_end) >= $start);
            $invoice = $posted->get($period);
            if (! $enrollment || ($class && $enrollment->class_id !== $class) || ($section && $enrollment->section_id !== $section) || (! $invoice && $identity->status !== 'active' && $enrollment->ends_on === null) || ($session && ($invoice?->academic_session_id ?? $enrollment->academic_session_id) !== $session)) {
                continue;
            }
            $covered = $coverage->first(fn ($c) => $c->from_period <= $period && $c->through_period >= $period);
            if (! $invoice && $covered) {
                $rows[] = ['period' => $period, 'academic_session_id' => $enrollment->academic_session_id, 'session_name' => $enrollment->session_name, 'invoice_id' => $covered->fee_invoice_id, 'voucher_number' => $covered->voucher_number, 'gross_minor' => null, 'discount_minor' => null, 'net_minor' => null, 'paid_minor' => null, 'balance_minor' => null, 'status' => 'opening_balance_covered'];

                continue;
            }
            $quote = $invoice ? null : app(FeeService::class)->tuitionQuote($student, $enrollment->academic_session_id, $period, $snapshot);
            $amount = $invoice ? $balances[$invoice->id] : null;
            $rows[] = ['period' => $period, 'academic_session_id' => $invoice?->academic_session_id ?? $enrollment->academic_session_id, 'session_name' => $invoice ? ($snapshot ? $snapshot->sessions->get($invoice->academic_session_id)?->name : DB::table('academic_sessions')->where('id', $invoice->academic_session_id)->value('name')) : $enrollment->session_name,
                'invoice_id' => $invoice?->id, 'voucher_number' => $invoice?->voucher_number,
                'gross_minor' => $invoice ? (int) $invoice->gross_minor : ($quote['gross_minor'] ?? null),
                'discount_minor' => $invoice ? (int) $invoice->discount_minor : ($quote['discount_minor'] ?? null),
                'net_minor' => $amount['net_total_minor'] ?? $quote['net_minor'] ?? null,
                'paid_minor' => $amount['paid_minor'] ?? 0, 'balance_minor' => $amount['balance_minor'] ?? null,
                'status' => $amount['status'] ?? ($quote ? 'not_billed' : 'needs_configuration')];
        }

        return $rows;
    }

    public function generate(User $actor, array $students, string $through, ?string $session = null, bool $currentOnly = false, ?string $class = null, ?string $section = null): array
    {
        return DB::transaction(function () use ($actor, $students, $through, $session, $currentOnly, $class, $section) {
            sort($students);
            $result = [];
            foreach ($students as $student) {
                DB::table('students')->where('id', $student)->lockForUpdate()->firstOrFail();
                $plan = $this->plan($student, $through, $session, $currentOnly, $class, $section);
                if (collect($plan)->contains(fn ($r) => $r['invoice_id'] === null) && $this->unreviewedOpenings($student)->isNotEmpty()) {
                    Domain::fail('opening_balance', 'Review opening balances and record their covered months before catch-up billing.');
                }
                foreach ($plan as $row) {
                    if ($row['status'] === 'needs_configuration') {
                        Domain::fail('fee_schedule', 'Configure historical tuition for '.$row['period'].' before generating fees.');
                    }
                    if ($row['invoice_id']) {
                        continue;
                    }
                    $result[] = app(FeeService::class)->generate($actor, ['student_id' => $student, 'academic_session_id' => $row['academic_session_id'], 'period' => $row['period'], 'kind' => 'tuition', 'issued_on' => now()->toDateString(), 'due_on' => now()->toDateString(), 'request_id' => (string) Str::uuid()]);
                }
            }

            return $result;
        });
    }

    public function warnings(array $students, string $through, ?string $session = null, ?string $class = null, ?int $limit = 30): array
    {
        $affected = [];
        $amount = $missing = $unknown = 0;
        foreach (array_chunk($students, 100) as $chunk) {
            $snapshot = new BillingSnapshot($chunk);
            foreach ($chunk as $id) {
                $rows = collect($this->plan($id, $through, $session, false, $class, null, $snapshot))->whereNull('invoice_id');
                if ($rows->isEmpty()) {
                    continue;
                }
                $identity = $snapshot->students->get($id);
                $total = (int) $rows->sum('net_minor');
                $amount += $total;
                $missing += $rows->count();
                $unknown += $rows->where('status', 'needs_configuration')->count();
                $affected[] = ['student_id' => $id, 'full_name' => $identity->full_name, 'registration_number' => $identity->registration_number, 'missing_months' => $rows->count(), 'unbilled_minor' => $total];
            }
        }

        return ['unbilled_minor' => $amount, 'missing_months' => $missing, 'months_needing_configuration' => $unknown, 'students_with_missing_months' => count($affected), 'unbilled_students' => array_slice($affected, 0, $limit ?? count($affected))];
    }

    public function unreviewedOpenings(string $student, ?BillingSnapshot $snapshot = null)
    {
        if ($snapshot) {
            return $snapshot->invoicesFor($student)->where('kind', 'opening_balance')->where('total_minor', '>', 0)->whereNotIn('id', $snapshot->coverageFor($student)->pluck('fee_invoice_id'))->map(fn ($i) => (object) ['id' => $i->id, 'voucher_number' => $i->voucher_number, 'period' => $i->period])->values();
        }

        return DB::table('fee_invoices')->where('student_id', $student)->where('kind', 'opening_balance')->where('total_minor', '>', 0)->whereNotIn('id', DB::table('opening_balance_coverage')->select('fee_invoice_id'))->get(['id', 'voucher_number', 'period']);
    }
}
