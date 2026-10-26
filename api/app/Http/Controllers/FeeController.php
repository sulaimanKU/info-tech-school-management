<?php

namespace App\Http\Controllers;

use App\Services\AcademicFilters;
use App\Services\Finance\FeeService;
use App\Services\Finance\InvoiceBalance;
use App\Services\SchoolAccess;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeController extends Controller
{
    public function __construct(private SchoolAccess $access, private InvoiceBalance $balance) {}

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

        return Domain::response([...$this->safeInvoice($i), ...$this->balance->calculate($i), 'lines' => DB::table('invoice_lines')->where('fee_invoice_id', $invoice)->get(['id', 'description', 'amount_minor', 'kind']), 'adjustments' => DB::table('invoice_adjustments')->where('fee_invoice_id', $invoice)->get(['id', 'amount_minor', 'reason', 'created_at'])]);
    }

    public function student(Request $r, string $student)
    {
        if (! $this->access->isParent($r->user())) {
            $this->access->require($r->user(), 'finance.read');
        }$this->access->student($r->user(), $student);
        $items = DB::table('fee_invoices')->where('student_id', $student)->orderByDesc('period')->get()->map(fn ($i) => [...$this->safeInvoice($i), ...$this->balance->calculate($i)]);
        $payments = DB::table('payment_allocations as a')->join('payments as p', 'p.id', '=', 'a.payment_id')->join('fee_invoices as i', 'i.id', '=', 'a.fee_invoice_id')->join('payment_methods as m', 'm.id', '=', 'p.payment_method_id')->where('i.student_id', $student)->orderByDesc('p.paid_on')->get(['p.id', 'p.receipt_number', 'p.paid_on', 'p.status', 'p.reference', 'a.id as allocation_id', 'a.amount_minor', 'a.fee_invoice_id', 'm.name as method'])->map(function ($p) use ($r) {
            $data = (array) $p;
            $data['refunded_minor'] = (int) DB::table('refund_allocations')->where('payment_allocation_id', $p->allocation_id)->sum('amount_minor');
            if ($this->access->isParent($r->user())) {
                $data['reference'] = $p->reference ? '****'.substr($p->reference, -4) : null;
            }

            return $data;
        });
        $discounts = DB::table('student_discounts as d')->join('discount_policies as p', 'p.id', '=', 'd.discount_policy_id')->where('d.student_id', $student)->where('d.status', 'approved')->whereNull('d.revoked_at')->get(['p.name', 'd.basis_points', 'd.starts_on', 'd.ends_on']);

        return Domain::response(['items' => $items, 'balance_minor' => $items->sum('balance_minor'), 'arrears_minor' => $items->filter(fn ($i) => $i['period'] < now()->format('Y-m'))->sum('balance_minor'), 'payments' => $payments, 'discounts' => $discounts, 'fee_schedule' => DB::table('fee_schedules as f')->whereExists(fn ($q) => $q->selectRaw('1')->from('enrollments as e')->join('sections as s', 's.id', '=', 'e.section_id')->where('e.student_id', $student)->where('e.status', 'active')->whereColumn('s.class_id', 'f.class_id')->whereColumn('s.academic_session_id', 'f.academic_session_id'))->get(['f.kind', 'f.amount_minor', 'f.effective_on'])]);
    }

    public function store(Request $r, FeeService $service)
    {
        $this->access->require($r->user(), 'finance.write');
        $v = $r->validate(['student_id' => 'required|uuid|exists:students,id', 'academic_session_id' => 'required|uuid|exists:academic_sessions,id', 'period' => 'required|date_format:Y-m', 'kind' => 'required|in:tuition,admission,promotion,additional,opening_balance', 'issued_on' => 'required|date|before_or_equal:today', 'due_on' => 'required|date|after_or_equal:issued_on', 'request_id' => 'required|uuid', 'charges' => 'sometimes|array|max:20', 'charges.*.description' => 'required|string|max:200', 'charges.*.amount_minor' => 'required|integer|min:1|max:1000000000']);
        $this->access->student($r->user(), $v['student_id']);
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
