<?php

namespace App\Http\Controllers;

use App\Services\Finance\FinancialLock;
use App\Services\Finance\EffectiveConfiguration;
use App\Services\Finance\PayrollService;
use App\Services\SchoolAccess;
use App\Support\Audit;
use App\Support\Domain;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    public function __construct(private SchoolAccess $access, private PayrollService $service) {}

    public function index(Request $r)
    {
        $this->access->require($r->user(), 'finance.read');

        $v = $r->validate(['period' => 'nullable|date_format:Y-m']);

        return Domain::response(['items' => DB::table('payroll_periods')->when($v['period'] ?? null, fn ($q, $period) => $q->where('period', $period))->orderByDesc('period')->get(), 'compensation' => DB::table('staff_compensation as c')->join('staff_profiles as s', 's.id', '=', 'c.staff_profile_id')->join('users as u', 'u.id', '=', 's.user_id')->orderByDesc('c.effective_on')->get(['c.*', 'u.name', 's.employee_number']), 'staff' => DB::table('staff_profiles as s')->join('users as u', 'u.id', '=', 's.user_id')->where('s.is_active', true)->where('u.is_active', true)->orderBy('u.name')->get(['s.id', 's.employee_number', 'u.name'])]);
    }

    public function show(Request $r, string $payroll)
    {
        $this->access->require($r->user(), 'finance.read');
        $p = DB::table('payroll_periods')->where('id', $payroll)->firstOrFail();

        return Domain::response([...(array) $p, 'method' => DB::table('payment_methods')->where('id', $p->payment_method_id)->value('name'), 'paid_minor' => $p->status === 'paid' ? (int) $p->total_minor : 0, 'items' => DB::table('payroll_items as i')->join('staff_profiles as s', 's.id', '=', 'i.staff_profile_id')->join('users as u', 'u.id', '=', 's.user_id')->where('i.payroll_period_id', $payroll)->get(['i.*', 'u.name', 's.employee_number'])]);
    }

    public function compensation(Request $r)
    {
        $this->access->require($r->user(), 'finance.approve');
        $v = $r->validate(['staff_profile_id' => 'required|uuid|exists:staff_profiles,id', 'base_salary_minor' => 'required|integer|min:0|max:1000000000', 'effective_on' => 'required|date']);
        $id = DB::transaction(function () use ($r, $v) {
            DB::table('staff_profiles')->where('id', $v['staff_profile_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();
            $id = app(EffectiveConfiguration::class)->save($r->user(), 'staff_compensation', array_intersect_key($v, array_flip(['staff_profile_id', 'effective_on'])), [...$v, 'approved_by' => $r->user()->id]);
            Audit::record('compensation_approved', $r->user()->id, 'staff_compensation', $id);

            return $id;
        });

        return Domain::response(['id' => $id], 201);
    }

    public function adjustment(Request $r)
    {
        $this->access->require($r->user(), 'finance.approve');
        $v = $r->validate(['staff_profile_id' => 'required|uuid|exists:staff_profiles,id', 'period' => 'required|date_format:Y-m', 'amount_minor' => 'required|integer|min:-1000000000|max:1000000000', 'reason' => 'required|string|max:500']);
        $id = DB::transaction(function () use ($r, $v) {
            app(FinancialLock::class)->request('payroll', $v['period']);
            if (DB::table('payroll_periods')->where('period', $v['period'])->exists()) {
                Domain::fail('period', 'Payroll already generated; use a later-period adjustment.');
            }
            $id = Domain::insert('payroll_adjustments', [...$v, 'approved_by' => $r->user()->id]);
            Audit::record('payroll_adjustment_approved', $r->user()->id, 'payroll_adjustments', $id);

            return $id;
        });

        return Domain::response(['id' => $id], 201);
    }

    public function store(Request $r)
    {
        $this->access->require($r->user(), 'finance.write');
        $v = $r->validate(['period' => 'required|date_format:Y-m']);

        return Domain::response(['id' => $this->service->generate($r->user(), $v['period'])], 201);
    }

    public function approve(Request $r, string $payroll)
    {
        $this->access->require($r->user(), 'finance.approve');
        $p = DB::table('payroll_periods')->where('id', $payroll)->firstOrFail();
        if (Carbon::parse($p->period.'-01')->endOfMonth()->isFuture()) {
            Domain::fail('period', 'Approve completed payroll periods only.');
        }$this->service->approve($r->user(), $payroll);

        return Domain::response(['approved' => true]);
    }

    public function pay(Request $r, string $payroll)
    {
        $this->access->require($r->user(), 'finance.write');
        $v = $r->validate(['payment_method_id' => 'required|uuid|exists:payment_methods,id', 'paid_on' => 'required|date|before_or_equal:today', 'reference' => 'nullable|string|max:200']);
        $this->service->pay($r->user(), $payroll, $v);

        return Domain::response(['paid' => true]);
    }

    public function mine(Request $r)
    {
        return app(StaffAccountController::class)->salary($r);
    }
}
