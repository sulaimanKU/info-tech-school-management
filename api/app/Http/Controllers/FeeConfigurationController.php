<?php

namespace App\Http\Controllers;

use App\Services\SchoolAccess;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeConfigurationController extends Controller
{
    public function __construct(private SchoolAccess $access) {}

    public function show(Request $r)
    {
        $this->access->require($r->user(), 'finance.read');

        return Domain::response(['schedules' => DB::table('fee_schedules as f')->join('classes as c', 'c.id', '=', 'f.class_id')->join('academic_sessions as s', 's.id', '=', 'f.academic_session_id')->select('f.*', 'c.name as class_name', 's.name as session_name')->orderByDesc('f.effective_on')->get(), 'discount_policies' => DB::table('discount_policies')->get(), 'payment_methods' => DB::table('payment_methods')->where('is_active', true)->get(), 'expense_categories' => DB::table('expense_categories')->get(), 'accounts' => DB::table('accounts')->get(), 'discount_stacking' => 'highest_only', 'expense_approval_threshold_minor' => 50000]);
    }

    public function schedule(Request $r)
    {
        $this->access->require($r->user(), 'finance.approve');
        $v = $r->validate(['academic_session_id' => 'required|uuid|exists:academic_sessions,id', 'class_id' => 'required|uuid|exists:classes,id', 'kind' => 'required|in:tuition,admission_promotion', 'amount_minor' => 'required|integer|min:0|max:1000000000', 'effective_on' => 'required|date']);

        return $this->insert($r, 'fee_schedules', [...$v, 'approved_by' => $r->user()->id]);
    }

    public function assignment(Request $r)
    {
        $this->access->require($r->user(), 'finance.approve');
        $v = $r->validate(['student_id' => 'required|uuid|exists:students,id', 'academic_session_id' => 'required|uuid|exists:academic_sessions,id', 'fee_category_id' => 'nullable|uuid|exists:fee_categories,id', 'tuition_override_minor' => 'nullable|integer|min:0|max:1000000000', 'effective_on' => 'required|date']);

        return $this->insert($r, 'student_fee_assignments', [...$v, 'approved_by' => $r->user()->id]);
    }

    public function policy(Request $r)
    {
        $this->access->require($r->user(), 'finance.approve');
        $v = $r->validate(['code' => 'required|alpha_dash|max:40|unique:discount_policies,code', 'name' => 'required|string|max:120', 'maximum_basis_points' => 'required|integer|min:0|max:10000', 'applies_to' => 'required|in:tuition,all']);

        return $this->insert($r, 'discount_policies', $v);
    }

    public function method(Request $r)
    {
        $this->access->require($r->user(), 'finance.approve');
        $v = $r->validate(['code' => 'required|alpha_dash|max:40|unique:payment_methods,code', 'name' => 'required|string|max:120', 'account_id' => 'required|uuid|exists:accounts,id', 'requires_verification' => 'required|boolean']);
        abort_unless(DB::table('accounts')->where('id', $v['account_id'])->where('type', 'asset')->where('is_active', true)->exists(), 422);

        return $this->insert($r, 'payment_methods', $v);
    }

    public function discounts(Request $r)
    {
        $this->access->require($r->user(), 'finance.read');
        $q = DB::table('student_discounts as d')->join('students as s', 's.id', '=', 'd.student_id')->join('discount_policies as p', 'p.id', '=', 'd.discount_policy_id');

        return Domain::response($q->orderByDesc('d.created_at')->paginate(30, ['d.*', 's.full_name', 'p.name as policy_name']));
    }

    public function requestDiscount(Request $r)
    {
        $this->access->require($r->user(), 'finance.write');
        $v = $r->validate(['student_id' => 'required|uuid|exists:students,id', 'academic_session_id' => 'required|uuid|exists:academic_sessions,id', 'discount_policy_id' => 'required|uuid|exists:discount_policies,id', 'basis_points' => 'required|integer|min:1|max:10000', 'starts_on' => 'required|date', 'ends_on' => 'required|date|after_or_equal:starts_on', 'eligibility_notes' => 'required|string|max:2000', 'evidence_file_id' => 'nullable|uuid|exists:private_files,id']);
        $policy = DB::table('discount_policies')->where('id', $v['discount_policy_id'])->where('is_active', true)->firstOrFail();
        if ($v['basis_points'] > $policy->maximum_basis_points) {
            Domain::fail('basis_points', 'Discount exceeds the policy maximum.');
        }if ($v['evidence_file_id'] ?? null) {
            $f = DB::table('private_files')->find($v['evidence_file_id']);
            abort_unless($f->student_id === $v['student_id'] && $f->purpose === 'document', 403);
        }

        return $this->insert($r, 'student_discounts', [...$v, 'requested_by' => $r->user()->id, 'status' => 'requested']);
    }

    public function approveDiscount(Request $r, string $discount)
    {
        $this->access->require($r->user(), 'finance.approve');
        $v = $r->validate(['eligibility_verified' => 'required|accepted']);
        DB::transaction(function () use ($r, $discount) {
            $d = DB::table('student_discounts')->where('id', $discount)->lockForUpdate()->firstOrFail();
            abort_unless($d->status === 'requested', 409);
            $p = DB::table('discount_policies')->find($d->discount_policy_id);
            if (! $p->is_active || $d->basis_points > $p->maximum_basis_points) {
                Domain::fail('discount', 'Policy is no longer eligible.');
            }if ($p->code === 'sibling') {
                $guardians = DB::table('parent_student_links')->where('student_id', $d->student_id)->where('is_active', true)->whereNotNull('verified_at')->pluck('parent_guardian_id');
                $siblings = DB::table('parent_student_links as l')->join('students as s', 's.id', '=', 'l.student_id')->whereIn('l.parent_guardian_id', $guardians)->where('l.is_active', true)->whereNotNull('l.verified_at')->where('s.status', 'active')->distinct()->count('s.id');
                if ($siblings < 2) {
                    Domain::fail('eligibility', 'Two active children with verified shared guardian links are required.');
                }
            }DB::table('student_discounts')->where('id', $discount)->update(['status' => 'approved', 'approved_by' => $r->user()->id, 'approved_at' => now(), 'updated_at' => now()]);
            Audit::record('student_discount_approved', $r->user()->id, 'student_discounts', $discount);
        });

        return Domain::response(['approved' => true]);
    }

    public function revokeDiscount(Request $r, string $discount)
    {
        $this->access->require($r->user(), 'finance.approve');
        DB::transaction(function () use ($r, $discount) {
            DB::table('student_discounts')->where('id', $discount)->firstOrFail();
            DB::table('student_discounts')->where('id', $discount)->update(['status' => 'revoked', 'revoked_at' => now(), 'updated_at' => now()]);
            Audit::record('student_discount_revoked', $r->user()->id, 'student_discounts', $discount);
        });

        return Domain::response(['revoked' => true]);
    }

    private function insert(Request $r, string $table, array $values)
    {
        $id = DB::transaction(function () use ($r, $table, $values) {
            $id = Domain::insert($table, $values);
            Audit::record('finance_configuration_created', $r->user()->id, $table, $id);

            return $id;
        });

        return Domain::response(['id' => $id], 201);
    }
}
