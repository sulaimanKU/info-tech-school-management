<?php

namespace App\Http\Controllers;

use App\Services\AcademicFilters;
use App\Services\Finance\FeeService;
use App\Services\Finance\InvoiceBalance;
use App\Services\Finance\MonthlyBilling;
use App\Services\Finance\PayrollService;
use App\Services\Finance\StudentFinance;
use App\Services\SchoolAccess;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeController extends Controller
{
    public function __construct(private SchoolAccess $access, private InvoiceBalance $balance) {}

    public function openingCoverage(Request $r, string $invoice)
    {
        $this->access->require($r->user(), 'finance.approve');
        $i = DB::table('fee_invoices')->where('id', $invoice)->firstOrFail();
        $this->access->student($r->user(), $i->student_id);
        $v = $r->validate(['covered_from' => 'required|date_format:Y-m', 'covered_through' => 'required|date_format:Y-m|after_or_equal:covered_from']);

        return Domain::response(['id' => app(MonthlyBilling::class)->coverOpening($r->user(), $invoice, $v['covered_from'], $v['covered_through'])]);
    }

    public function monthly(Request $r)
    {
        $this->access->require($r->user(), 'finance.write');
        $filters = app(AcademicFilters::class);
        $v = $filters->values($r, ['through' => 'required|date_format:Y-m', 'preview' => 'sometimes|boolean', 'current_only' => 'sometimes|boolean']);
        if ($v['through'] > now()->format('Y-m')) {
            Domain::fail('through', 'Future months cannot be generated.');
        }
        $students = $filters->students($this->access->students($r->user()), $r, array_diff_key($v, ['status' => true]));
        if (! empty($v['student_id'])) {
            $students->where('students.id', $v['student_id']);
        }
        $ids = $students->orderBy('students.id')->pluck('students.id')->all();
        if (count($ids) > 500) {
            Domain::fail('class_id', 'Choose a class or student; batches are limited to 500 students.');
        }
        $billing = app(MonthlyBilling::class);
        $rows = [];
        foreach ($ids as $id) {
            $identity = DB::table('students')->find($id);
            foreach ($billing->plan($id, $v['through'], $v['academic_session_id'] ?? null, $v['current_only'] ?? false, $v['class_id'] ?? null, $v['section_id'] ?? null) as $row) {
                $rows[] = ['student_id' => $id, 'registration_number' => $identity->registration_number, 'full_name' => $identity->full_name, ...$row];
            }
        }
        $generated = ($v['preview'] ?? true) ? [] : $billing->generate($r->user(), $ids, $v['through'], $v['academic_session_id'] ?? null, $v['current_only'] ?? false, $v['class_id'] ?? null, $v['section_id'] ?? null);
        $summary = ['eligible_months' => count($rows), 'already_billed' => collect($rows)->filter(fn ($r) => $r['invoice_id'] !== null && $r['status'] !== 'opening_balance_covered')->count(), 'covered_months' => collect($rows)->where('status', 'opening_balance_covered')->count(), 'ready_to_generate' => collect($rows)->where('status', 'not_billed')->count(), 'needs_configuration' => collect($rows)->where('status', 'needs_configuration')->count()];

        return Domain::response([...$summary, 'student_count' => count($ids), 'missing_months' => collect($rows)->whereNull('invoice_id')->count(), 'unbilled_minor' => (int) collect($rows)->whereNull('invoice_id')->sum('net_minor'), 'opening_balances_needing_review' => collect($ids)->flatMap(fn ($id) => $billing->unreviewedOpenings($id))->values(), 'periods' => $rows, 'generated_ids' => $generated]);
    }

    public function summary(Request $r, bool $report = false)
    {
        $this->access->require($r->user(), 'finance.read');
        $filters = app(AcademicFilters::class);
        $v = $filters->values($r, ['period' => 'nullable|date_format:Y-m']);
        $students = $filters->students($this->access->students($r->user()), $r, array_diff_key($v, ['status' => true]));
        $invoices = DB::table('fee_invoices')->whereIn('student_id', $students->select('students.id'))
            ->when($v['academic_session_id'] ?? null, fn ($q, $id) => $q->where('academic_session_id', $id))
            ->when($v['from'] ?? null, fn ($q, $date) => $q->where('issued_on', '>=', $date))
            ->when($v['to'] ?? null, fn ($q, $date) => $q->where('issued_on', '<=', $date))->get();
        $amounts = collect($this->balance->many($invoices));
        $period = $v['period'] ?? now()->format('Y-m');
        $studentBalances = $invoices->groupBy('student_id')->map(function ($rows, $student) use ($amounts, $period) {
            $a = $rows->map(fn ($i) => $amounts[$i->id]);
            $identity = DB::table('students')->find($student);

            return ['student_id' => $student, 'full_name' => $identity->full_name, 'registration_number' => $identity->registration_number, 'total_minor' => (int) $a->sum('net_total_minor'), 'paid_minor' => (int) $a->sum('paid_minor'), 'balance_minor' => (int) $a->sum('balance_minor'), 'arrears_minor' => (int) $rows->filter(fn ($i) => $i->period < $period)->sum(fn ($i) => $amounts[$i->id]['balance_minor']), 'current_period_due_minor' => (int) $rows->filter(fn ($i) => $i->period === $period)->sum(fn ($i) => $amounts[$i->id]['balance_minor'])];
        })->values();
        $period = $v['period'] ?? now()->format('Y-m');
        $data = ['billing_basis' => 'posted_vouchers', 'period' => $period, 'total_minor' => (int) $amounts->sum('net_total_minor'), 'paid_minor' => (int) $amounts->sum('paid_minor'), 'discount_minor' => (int) $invoices->sum('discount_minor'), 'balance_minor' => (int) $amounts->sum('balance_minor'), 'arrears_minor' => (int) $invoices->filter(fn ($i) => $i->period < $period)->sum(fn ($i) => $amounts[$i->id]['balance_minor']), 'students_unpaid' => $studentBalances->filter(fn ($s) => $s['balance_minor'] > 0 && $s['paid_minor'] === 0)->count(), 'students_partially_paid' => $studentBalances->filter(fn ($s) => $s['balance_minor'] > 0 && $s['paid_minor'] > 0)->count(), 'student_balances' => $studentBalances->sortByDesc('balance_minor')->when(! $report, fn ($rows) => $rows->take(30))->values()];
        $data += app(MonthlyBilling::class)->warnings((clone $students)->pluck('students.id')->all(), $period, $v['academic_session_id'] ?? null, $v['class_id'] ?? null, $report ? null : 30);
        $data['recent_payments'] = DB::table('payments as p')->join('payment_methods as m', 'm.id', '=', 'p.payment_method_id')->join('users as u', 'u.id', '=', 'p.recorded_by')
            ->whereIn('p.id', DB::table('payment_allocations')->whereIn('fee_invoice_id', $invoices->pluck('id'))->select('payment_id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('payment_allocations as a')->join('fee_invoices as i', 'i.id', '=', 'a.fee_invoice_id')->whereColumn('a.payment_id', 'p.id')->whereNotIn('i.student_id', $this->access->students($r->user())->select('students.id')))
            ->orderByDesc('p.paid_on')->orderByDesc('p.created_at')->limit(10)->get(['p.id', 'p.receipt_number', 'p.paid_on', 'p.amount_minor', 'p.status', 'p.reference', 'p.notes', 'm.name as method', 'u.name as recorded_by_name'])->map(fn ($p) => [...(array) $p, 'amount_minor' => (int) DB::table('payment_allocations')->where('payment_id', $p->id)->whereIn('fee_invoice_id', $invoices->pluck('id'))->sum('amount_minor')]);
        if ($r->user()->hasPermission('payroll.read')) {
            $payroll = DB::table('payroll_periods')->whereIn('status', ['approved', 'paid'])->when($v['period'] ?? null, fn ($q, $period) => $q->where('period', $period))->get()->map(fn ($p) => app(PayrollService::class)->details($p));
            $data['payroll'] = ['total_minor' => (int) $payroll->sum('total_minor'), 'paid_minor' => (int) $payroll->sum('paid_minor'), 'balance_minor' => (int) $payroll->sum('balance_minor')];
        }

        return Domain::response($data);
    }

    public function index(Request $r)
    {
        $this->access->require($r->user(), 'finance.read');
        $filters = app(AcademicFilters::class);
        $v = $filters->values($r, ['period' => 'nullable|date_format:Y-m', 'status' => 'nullable|in:paid,unpaid,partially_paid,overdue,waived_adjusted']);
        $q = DB::table('fee_invoices as f')->join('students as s', 's.id', '=', 'f.student_id');
        $students = $filters->students($this->access->students($r->user()), $r, array_diff_key($v, array_flip(['status'])));
        $q->whereIn('f.student_id', $students->select('students.id'));
        if (! empty($v['from'])) {
            $q->whereDate('f.issued_on', '>=', $v['from']);
        }
        if (! empty($v['to'])) {
            $q->whereDate('f.issued_on', '<=', $v['to']);
        }
        if (! empty($v['status'])) {
            $paid = DB::table('payment_allocations as pa')->join('payments as pp', 'pp.id', '=', 'pa.payment_id')->whereColumn('pa.fee_invoice_id', 'f.id')->where('pp.status', 'verified')->selectRaw('coalesce(sum(pa.amount_minor),0)');
            $refund = DB::table('refund_allocations as ra')->join('payment_allocations as pa', 'pa.id', '=', 'ra.payment_allocation_id')->whereColumn('pa.fee_invoice_id', 'f.id')->selectRaw('coalesce(sum(ra.amount_minor),0)');
            $adjust = DB::table('invoice_adjustments')->whereColumn('fee_invoice_id', 'f.id')->selectRaw('coalesce(sum(amount_minor),0)');
            $net = '('.$paid->toSql().') - ('.$refund->toSql().')';
            $total = 'f.total_minor + ('.$adjust->toSql().')';
            $bindings = [...$paid->getBindings(), ...$refund->getBindings(), ...$adjust->getBindings()];
            if ($v['status'] === 'waived_adjusted') {
                $q->whereRaw($total.' = 0', $adjust->getBindings());
            } else {
                $q->whereRaw($total.' <> 0', $adjust->getBindings());
                if ($v['status'] === 'paid') {
                    $q->whereRaw($net.' = '.$total, $bindings);
                } elseif ($v['status'] === 'partially_paid') {
                    $q->whereRaw($net.' > 0', [...$paid->getBindings(), ...$refund->getBindings()])->whereRaw($net.' <> '.$total, $bindings);
                } else {
                    $q->whereRaw($net.' <= 0', [...$paid->getBindings(), ...$refund->getBindings()])->whereRaw($net.' <> '.$total, $bindings)->where('f.due_on', $v['status'] === 'overdue' ? '<' : '>=', now()->toDateString());
                }
            }
        }
        foreach (['student_id', 'period'] as $key) {
            if (isset($v[$key])) {
                $q->where('f.'.$key, $v[$key]);
            }
        }

        return Domain::response($q->orderByDesc('f.issued_on')->paginate(30, ['f.*', 's.full_name'])->through(fn ($i) => [...$this->safeInvoice($i), 'full_name' => $i->full_name, ...$this->balance->calculate($i)]));
    }

    public function show(Request $r, string $invoice)
    {
        $i = DB::table('fee_invoices')->where('id', $invoice)->firstOrFail();
        $this->authorizeInvoice($r, $i);

        return Domain::response([...$this->safeInvoice($i), ...$this->balance->calculate($i), 'opening_coverage' => DB::table('opening_balance_coverage')->where('fee_invoice_id', $invoice)->first(['from_period', 'through_period']), 'lines' => DB::table('invoice_lines')->where('fee_invoice_id', $invoice)->get(['id', 'description', 'amount_minor', 'kind']), 'adjustments' => DB::table('invoice_adjustments')->where('fee_invoice_id', $invoice)->get(['id', 'amount_minor', 'reason', 'created_at'])]);
    }

    public function student(Request $r, string $student)
    {
        if (! $this->access->isParent($r->user())) {
            $this->access->require($r->user(), 'finance.read');
        }$identity = $this->access->student($r->user(), $student);
        $v = $r->validate(['academic_session_id' => 'nullable|uuid|exists:academic_sessions,id', 'period' => 'nullable|date_format:Y-m']);
        $snapshot = app(StudentFinance::class)->snapshot($student, $v['academic_session_id'] ?? null, $v['period'] ?? now()->format('Y-m'));
        $payments = DB::table('payment_allocations as a')->join('payments as p', 'p.id', '=', 'a.payment_id')->join('fee_invoices as i', 'i.id', '=', 'a.fee_invoice_id')->join('payment_methods as m', 'm.id', '=', 'p.payment_method_id')->where('i.student_id', $student)->when($snapshot['filter_session_id'], fn ($q, $session) => $q->where('i.academic_session_id', $session))->orderByDesc('p.paid_on')->orderByDesc('p.created_at')->get(['p.id', 'p.receipt_number', 'p.paid_on', 'p.status', 'p.reference', 'p.notes', 'p.recorded_by', 'a.id as allocation_id', 'a.amount_minor', 'a.fee_invoice_id', 'm.name as method'])->map(function ($p) use ($r) {
            $data = (array) $p;
            $data['recorded_by_name'] = DB::table('users')->where('id', $p->recorded_by)->value('name');
            $data['refunded_minor'] = (int) DB::table('refund_allocations')->where('payment_allocation_id', $p->allocation_id)->sum('amount_minor');
            if ($this->access->isParent($r->user())) {
                unset($data['notes'], $data['recorded_by'], $data['recorded_by_name']);
                $data['reference'] = $p->reference ? '****'.substr($p->reference, -4) : null;
            }

            return $data;
        });
        $discounts = DB::table('student_discounts as d')->join('discount_policies as p', 'p.id', '=', 'd.discount_policy_id')->where('d.student_id', $student)->when($snapshot['academic_session_id'], fn ($q, $session) => $q->where('d.academic_session_id', $session))->where('d.status', 'approved')->whereNull('d.revoked_at')->get(['p.name', 'd.basis_points', 'd.starts_on', 'd.ends_on']);

        $payments = $payments->groupBy('id')->map(fn ($rows) => [...$rows->first(), 'amount_minor' => (int) $rows->sum('amount_minor'), 'refunded_minor' => (int) $rows->sum('refunded_minor')])->values();
        $class = $snapshot['class'];
        unset($snapshot['class']);

        return Domain::response([...$snapshot, 'student' => ['id' => $student, 'full_name' => $identity->full_name, 'registration_number' => $identity->registration_number, 'admitted_on' => $identity->admitted_on, ...$class], 'payments' => $payments, 'discounts' => $discounts]);
    }

    public function store(Request $r, FeeService $service)
    {
        $this->access->require($r->user(), 'finance.write');
        $v = $r->validate(['student_id' => 'required|uuid|exists:students,id', 'academic_session_id' => 'required|uuid|exists:academic_sessions,id', 'period' => 'required|date_format:Y-m', 'kind' => 'required|in:tuition,admission,promotion,additional,opening_balance', 'issued_on' => 'required|date|before_or_equal:today', 'due_on' => 'required|date|after_or_equal:issued_on', 'request_id' => 'required|uuid', 'charges' => 'sometimes|array|max:20', 'charges.*.description' => 'required|string|max:200', 'charges.*.amount_minor' => 'required|integer|min:1|max:1000000000']);
        $this->access->student($r->user(), $v['student_id']);
        if ($v['kind'] === 'opening_balance' && $v['period'] < substr(DB::table('students')->find($v['student_id'])->admitted_on, 0, 7)) {
            $this->access->require($r->user(), 'finance.approve');
        }
        $id = $service->generate($r->user(), $v);

        return Domain::response(['id' => $id], 201);
    }

    public function adjust(Request $r, string $invoice, FeeService $service)
    {
        $this->access->require($r->user(), 'finance.approve');
        $i = DB::table('fee_invoices')->where('id', $invoice)->firstOrFail();
        $this->access->student($r->user(), $i->student_id);
        $v = $r->validate(['amount_minor' => 'required|integer|not_in:0|min:-1000000000|max:1000000000', 'reason' => 'required|string|max:500', 'request_id' => 'required|uuid']);

        return Domain::response(['id' => $service->adjust($r->user(), $invoice, $v)], 201);
    }

    public function authorizeInvoice(Request $r, object $invoice): void
    {
        if ($this->access->isParent($r->user())) {
            $this->access->student($r->user(), $invoice->student_id);
        } else {
            $this->access->require($r->user(), 'finance.read');
            $this->access->student($r->user(), $invoice->student_id);
        }
    }

    private function safeInvoice(object $i): array
    {
        return array_intersect_key((array) $i, array_flip(['id', 'voucher_number', 'student_id', 'academic_session_id', 'period', 'kind', 'issued_on', 'due_on', 'gross_minor', 'discount_minor', 'total_minor']));
    }
}
