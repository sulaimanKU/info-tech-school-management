<?php

namespace App\Services\Finance;

use App\Models\User;
use App\Support\Domain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AdmissionFinance
{
    public static function rules(): array
    {
        return ['request_id' => 'sometimes|required|uuid', 'admission_finance' => 'sometimes|array',
            'admission_finance.tuition_override_minor' => 'sometimes|required|integer|min:0|max:1000000000',
            'admission_finance.admission_fee' => 'sometimes|boolean', 'admission_finance.bill_tuition' => 'sometimes|boolean',
            'admission_finance.charges' => 'sometimes|array|max:20', 'admission_finance.charges.*.description' => 'required|string|max:200', 'admission_finance.charges.*.amount_minor' => 'required|integer|min:1|max:1000000000',
            'admission_finance.opening_balance_minor' => 'sometimes|required|integer|min:1|max:1000000000',
            'admission_finance.covered_from' => 'required_with:admission_finance.covered_through|date_format:Y-m', 'admission_finance.covered_through' => 'required_with:admission_finance.covered_from|date_format:Y-m|after_or_equal:admission_finance.covered_from',
            'admission_finance.payment' => 'sometimes|array', 'admission_finance.payment.amount_minor' => 'required_with:admission_finance.payment|integer|min:1|max:1000000000',
            'admission_finance.payment.payment_method_id' => 'required_with:admission_finance.payment|uuid|exists:payment_methods,id', 'admission_finance.payment.paid_on' => 'required_with:admission_finance.payment|date|before_or_equal:today',
            'admission_finance.payment.reference' => 'nullable|string|max:200', 'admission_finance.payment.notes' => 'nullable|string|max:500'];
    }

    public function apply(User $actor, string $student, array $values): void
    {
        if (! $values) {
            return;
        }
        if (isset($values['covered_from']) && ! isset($values['opening_balance_minor'])) {
            Domain::fail('admission_finance.covered_from', 'Coverage requires an explicit opening balance.');
        }
        $identity = DB::table('students')->find($student);
        $enrollment = DB::table('enrollments')->where('student_id', $student)->firstOrFail();
        if (isset($values['tuition_override_minor'])) {
            abort_unless($actor->hasPermission('finance.configure'), 403);
            app(EffectiveConfiguration::class)->save($actor, 'student_fee_assignments', ['student_id' => $student, 'academic_session_id' => $enrollment->academic_session_id, 'effective_on' => $identity->admitted_on], ['tuition_override_minor' => $values['tuition_override_minor'], 'approved_by' => $actor->id]);
        }
        $needsWrite = ($values['bill_tuition'] ?? false) || ($values['admission_fee'] ?? false) || ! empty($values['charges']) || isset($values['opening_balance_minor'], $values['payment']);
        if (isset($values['opening_balance_minor']) || isset($values['payment'])) {
            $needsWrite = true;
        }
        if (! $needsWrite) {
            return;
        }
        abort_unless($actor->hasPermission('finance.write'), 403);
        $base = ['student_id' => $student, 'academic_session_id' => $enrollment->academic_session_id, 'period' => substr($identity->admitted_on, 0, 7), 'issued_on' => now()->toDateString(), 'due_on' => now()->toDateString()];
        foreach (['admission' => $values['admission_fee'] ?? false, 'tuition' => $values['bill_tuition'] ?? false, 'additional' => ! empty($values['charges']), 'opening_balance' => isset($values['opening_balance_minor'])] as $kind => $enabled) {
            if (! $enabled) {
                continue;
            }
            $charges = $kind === 'additional' ? $values['charges'] : ($kind === 'opening_balance' ? [['description' => 'Authorized opening/historical balance', 'amount_minor' => $values['opening_balance_minor']]] : []);
            $invoice = app(FeeService::class)->generate($actor, [...$base, 'kind' => $kind, 'charges' => $charges, 'request_id' => (string) Str::uuid()]);
            if ($kind === 'opening_balance' && isset($values['covered_from'], $values['covered_through'])) {
                app(MonthlyBilling::class)->coverOpening($actor, $invoice, $values['covered_from'], $values['covered_through']);
            }
        }
        if (isset($values['payment'])) {
            $payment = $values['payment'];
            $remaining = $payment['amount_minor'];
            $allocations = [];
            foreach (DB::table('fee_invoices')->where('student_id', $student)->orderBy('period')->orderByRaw("CASE kind WHEN 'opening_balance' THEN 0 WHEN 'admission' THEN 1 WHEN 'additional' THEN 2 ELSE 3 END")->orderBy('id')->get() as $invoice) {
                $amount = min($remaining, app(InvoiceBalance::class)->calculate($invoice)['balance_minor']);
                if ($amount > 0) {
                    $allocations[] = ['fee_invoice_id' => $invoice->id, 'amount_minor' => $amount];
                    $remaining -= $amount;
                }
            }
            if ($remaining > 0 || ! $allocations) {
                Domain::fail('admission_finance.payment.amount_minor', 'Generate sufficient admission vouchers before recording the amount received.');
            }
            app(PaymentService::class)->record($actor, [...$payment, 'request_id' => (string) Str::uuid(), 'allocations' => $allocations]);
        }
    }
}
