<?php

namespace App\Http\Controllers;

use App\Services\AcademicFilters;
use App\Services\Finance\PaymentService;
use App\Services\SchoolAccess;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(private SchoolAccess $access, private PaymentService $service) {}

    private function scopedPayment(Request $r, string $payment): void
    {
        DB::table('payments')->where('id', $payment)->firstOrFail();
        abort_if(DB::table('payment_allocations as scope_a')->join('fee_invoices as scope_i', 'scope_i.id', '=', 'scope_a.fee_invoice_id')->where('scope_a.payment_id', $payment)->whereNotIn('scope_i.student_id', $this->access->students($r->user())->select('students.id'))->exists(), 404);
    }

    public function index(Request $r)
    {
        $this->access->require($r->user(), 'finance.read');

        $filters = app(AcademicFilters::class);
        $v = $filters->values($r, ['status' => 'nullable|in:pending,verified,canceled,refunded']);
        $q = DB::table('payments')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('payment_allocations as restricted_a')->join('fee_invoices as restricted_i', 'restricted_i.id', '=', 'restricted_a.fee_invoice_id')->whereColumn('restricted_a.payment_id', 'payments.id')->whereNotIn('restricted_i.student_id', $this->access->students($r->user())->select('students.id')));
        $students = $filters->students($this->access->students($r->user()), $r, array_diff_key($v, array_flip(['status'])));
        $invoices = DB::table('fee_invoices')->whereIn('student_id', $students->select('students.id'))->select('id');
        $q->whereIn('id', DB::table('payment_allocations')->whereIn('fee_invoice_id', $invoices)->select('payment_id'));
        if (! empty($v['status'])) {
            if ($v['status'] === 'refunded') {
                $q->whereIn('id', DB::table('payment_refunds')->select('payment_id'));
            } else {
                $q->where('status', $v['status']);
            }
        }
        if (! empty($v['from'])) {
            $q->whereDate('paid_on', '>=', $v['from']);
        }if (! empty($v['to'])) {
            $q->whereDate('paid_on', '<=', $v['to']);
        }

        return Domain::response($q->orderByDesc('created_at')->paginate(30, ['id', 'receipt_number', 'payment_method_id', 'paid_on', 'amount_minor', 'reference', 'proof_file_id', 'status']));
    }

    public function show(Request $r, string $payment)
    {
        $this->access->require($r->user(), 'finance.read');
        $p = DB::table('payments')->where('id', $payment)->firstOrFail();
        abort_if(DB::table('payment_allocations as scope_a')->join('fee_invoices as scope_i', 'scope_i.id', '=', 'scope_a.fee_invoice_id')->where('scope_a.payment_id', $payment)->whereNotIn('scope_i.student_id', $this->access->students($r->user())->select('students.id'))->exists(), 404);
        $data = array_intersect_key((array) $p, array_flip(['id', 'receipt_number', 'paid_on', 'amount_minor', 'reference', 'proof_file_id', 'status', 'payment_method_id']));
        $data['proof_file'] = $p->proof_file_id ? DB::table('private_files')->where('id', $p->proof_file_id)->first(['id', 'mime', 'size']) : null;
        $data['allocations'] = DB::table('payment_allocations as a')->join('fee_invoices as i', 'i.id', '=', 'a.fee_invoice_id')->where('a.payment_id', $payment)->get(['a.id', 'a.fee_invoice_id', 'a.amount_minor', 'i.student_id', 'i.voucher_number'])->map(fn ($a) => [...(array) $a, 'refunded_minor' => (int) DB::table('refund_allocations')->where('payment_allocation_id', $a->id)->sum('amount_minor')]);
        $data['refunds'] = DB::table('payment_refunds')->where('payment_id', $payment)->get(['id', 'number', 'refunded_on', 'amount_minor', 'reason']);

        return Domain::response($data);
    }

    public function store(Request $r)
    {
        $this->access->require($r->user(), 'finance.write');
        $v = $r->validate(['payment_method_id' => 'required|uuid|exists:payment_methods,id', 'paid_on' => 'required|date|before_or_equal:today', 'amount_minor' => 'required|integer|min:1|max:1000000000', 'reference' => 'nullable|string|max:200', 'proof_file_id' => 'nullable|uuid|exists:private_files,id', 'request_id' => 'required|uuid', 'allocations' => 'required|array|min:1|max:50', 'allocations.*.fee_invoice_id' => 'required|uuid|distinct|exists:fee_invoices,id', 'allocations.*.amount_minor' => 'required|integer|min:1|max:1000000000']);

        foreach ($v['allocations'] as $allocation) {
            $invoice = DB::table('fee_invoices')->where('id', $allocation['fee_invoice_id'])->firstOrFail();
            $this->access->student($r->user(), $invoice->student_id);
        }

        return Domain::response(['id' => $this->service->record($r->user(), $v)], 201);
    }

    public function verify(Request $r, string $payment)
    {
        $this->access->require($r->user(), 'finance.approve');
        $this->scopedPayment($r, $payment);
        $this->service->verify($r->user(), $payment);

        return Domain::response(['verified' => true]);
    }

    public function cancel(Request $r, string $payment)
    {
        $this->access->require($r->user(), 'finance.approve');
        $this->scopedPayment($r, $payment);
        DB::transaction(function () use ($r, $payment) {
            $p = DB::table('payments')->where('id', $payment)->lockForUpdate()->firstOrFail();
            abort_unless($p->status === 'pending', 409);
            DB::table('payments')->where('id', $payment)->update(['status' => 'canceled', 'updated_at' => now()]);
            Audit::record('pending_payment_canceled', $r->user()->id, 'payments', $payment);
        });

        return Domain::response(['canceled' => true]);
    }

    public function refund(Request $r, string $payment)
    {
        $this->access->require($r->user(), 'finance.approve');
        $this->scopedPayment($r, $payment);
        $v = $r->validate(['refunded_on' => 'required|date|before_or_equal:today', 'reason' => 'required|string|max:500', 'request_id' => 'required|uuid', 'allocations' => 'required|array|min:1|max:50', 'allocations.*.payment_allocation_id' => 'required|uuid|distinct|exists:payment_allocations,id', 'allocations.*.amount_minor' => 'required|integer|min:1|max:1000000000']);

        return Domain::response(['id' => $this->service->refund($r->user(), $payment, $v)], 201);
    }
}
