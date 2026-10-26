<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->unsignedBigInteger('next_number')->default(1);
        });
        Schema::create('accounts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('type', 20);
            $t->boolean('is_active')->default(true);
            $t->timestampsTz();
        });
        Schema::create('journal_entries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('number')->unique();
            $t->string('posting_key')->unique();
            $t->char('payload_hash', 64);
            $t->date('date');
            $t->string('description', 300);
            $t->string('reference_type', 40);
            $t->uuid('reference_id');
            $t->uuid('reversal_of')->nullable()->unique();
            $t->foreignUuid('posted_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->index(['reference_type', 'reference_id']);
        });
        Schema::table('journal_entries', fn (Blueprint $t) => $t->foreign('reversal_of')->references('id')->on('journal_entries')->restrictOnDelete());
        Schema::create('journal_lines', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('journal_entry_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('account_id')->constrained()->restrictOnDelete();
            $t->bigInteger('debit_minor')->default(0);
            $t->bigInteger('credit_minor')->default(0);
            $t->char('currency', 3)->default('PKR');
            $t->timestampsTz();
        });
        Schema::create('fee_schedules', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('academic_session_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('class_id')->constrained()->restrictOnDelete();
            $t->string('kind', 30);
            $t->bigInteger('amount_minor');
            $t->date('effective_on');
            $t->foreignUuid('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['academic_session_id', 'class_id', 'kind', 'effective_on'], 'fee_schedule_effective_unique');
        });
        Schema::create('student_fee_assignments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('student_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('academic_session_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('fee_category_id')->nullable()->constrained()->restrictOnDelete();
            $t->bigInteger('tuition_override_minor')->nullable();
            $t->date('effective_on');
            $t->foreignUuid('approved_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['student_id', 'academic_session_id', 'effective_on'], 'student_fee_effective_unique');
        });
        Schema::create('discount_policies', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code')->unique();
            $t->string('name');
            $t->unsignedInteger('maximum_basis_points');
            $t->string('applies_to', 20)->default('tuition');
            $t->boolean('is_active')->default(true);
            $t->timestampsTz();
        });
        Schema::create('student_discounts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('student_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('academic_session_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('discount_policy_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('basis_points');
            $t->date('starts_on');
            $t->date('ends_on');
            $t->text('eligibility_notes');
            $t->foreignUuid('evidence_file_id')->nullable()->constrained('private_files')->restrictOnDelete();
            $t->string('status', 20)->default('requested');
            $t->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('approved_at')->nullable();
            $t->timestampTz('revoked_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('fee_invoices', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('voucher_number')->unique();
            $t->foreignUuid('student_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('academic_session_id')->constrained()->restrictOnDelete();
            $t->string('period', 7);
            $t->string('kind', 30);
            $t->date('issued_on');
            $t->date('due_on');
            $t->bigInteger('gross_minor');
            $t->bigInteger('discount_minor');
            $t->bigInteger('total_minor');
            $t->uuid('request_id')->unique();
            $t->char('payload_hash', 64);
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['student_id', 'academic_session_id', 'period', 'kind'], 'invoice_period_unique');
        });
        Schema::create('invoice_lines', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('fee_invoice_id')->constrained()->restrictOnDelete();
            $t->string('description', 200);
            $t->bigInteger('amount_minor');
            $t->string('kind', 30);
            $t->timestampsTz();
        });
        Schema::create('invoice_adjustments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('fee_invoice_id')->constrained()->restrictOnDelete();
            $t->bigInteger('amount_minor');
            $t->string('reason', 500);
            $t->uuid('request_id')->unique();
            $t->foreignUuid('approved_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
        });
        Schema::create('payment_methods', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code')->unique();
            $t->string('name');
            $t->foreignUuid('account_id')->constrained()->restrictOnDelete();
            $t->boolean('requires_verification')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestampsTz();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('receipt_number')->nullable()->unique();
            $t->foreignUuid('payment_method_id')->constrained()->restrictOnDelete();
            $t->date('paid_on');
            $t->bigInteger('amount_minor');
            $t->string('reference', 200)->nullable();
            $t->foreignUuid('proof_file_id')->nullable()->constrained('private_files')->restrictOnDelete();
            $t->string('status', 20);
            $t->uuid('request_id')->unique();
            $t->char('payload_hash', 64);
            $t->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('verified_at')->nullable();
            $t->timestampsTz();
            $t->unique(['payment_method_id', 'reference']);
        });
        Schema::create('payment_allocations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('payment_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('fee_invoice_id')->constrained()->restrictOnDelete();
            $t->bigInteger('amount_minor');
            $t->timestampsTz();
            $t->unique(['payment_id', 'fee_invoice_id']);
        });
        Schema::create('payment_refunds', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('payment_id')->constrained()->restrictOnDelete();
            $t->string('number')->unique();
            $t->date('refunded_on');
            $t->bigInteger('amount_minor');
            $t->string('reason', 500);
            $t->uuid('request_id')->unique();
            $t->char('payload_hash', 64);
            $t->foreignUuid('approved_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
        });
        Schema::create('refund_allocations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('payment_refund_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('payment_allocation_id')->constrained()->restrictOnDelete();
            $t->bigInteger('amount_minor');
            $t->timestampsTz();
            $t->unique(['payment_refund_id', 'payment_allocation_id'], 'refund_allocation_unique');
        });
        Schema::create('expense_categories', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code')->unique();
            $t->string('name');
            $t->timestampsTz();
        });
        Schema::create('expenses', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('expense_category_id')->constrained()->restrictOnDelete();
            $t->string('description', 500);
            $t->bigInteger('amount_minor');
            $t->date('expense_on');
            $t->string('status', 20)->default('requested');
            $t->foreignUuid('proof_file_id')->nullable()->constrained('private_files')->restrictOnDelete();
            $t->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('approved_at')->nullable();
            $t->foreignUuid('paid_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignUuid('payment_method_id')->nullable()->constrained()->restrictOnDelete();
            $t->date('paid_on')->nullable();
            $t->string('reference')->nullable();
            $t->timestampsTz();
        });
        Schema::create('staff_compensation', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('staff_profile_id')->constrained()->restrictOnDelete();
            $t->bigInteger('base_salary_minor');
            $t->date('effective_on');
            $t->foreignUuid('approved_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->unique(['staff_profile_id', 'effective_on']);
        });
        Schema::create('payroll_adjustments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('staff_profile_id')->constrained()->restrictOnDelete();
            $t->string('period', 7);
            $t->bigInteger('amount_minor');
            $t->string('reason', 500);
            $t->foreignUuid('approved_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
        });
        Schema::create('payroll_periods', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('period', 7)->unique();
            $t->string('status', 20)->default('draft');
            $t->bigInteger('total_minor');
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestampTz('approved_at')->nullable();
            $t->date('paid_on')->nullable();
            $t->foreignUuid('payment_method_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('reference')->nullable();
            $t->timestampsTz();
        });
        Schema::create('payroll_items', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('payroll_period_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('staff_profile_id')->constrained()->restrictOnDelete();
            $t->bigInteger('base_salary_minor');
            $t->bigInteger('adjustment_minor');
            $t->bigInteger('net_salary_minor');
            $t->timestampsTz();
            $t->unique(['payroll_period_id', 'staff_profile_id']);
        });
        if (DB::getDriverName() === 'pgsql') {
            foreach (['fee_schedules' => 'amount_minor >= 0', 'student_fee_assignments' => 'tuition_override_minor IS NULL OR tuition_override_minor >= 0', 'discount_policies' => 'maximum_basis_points BETWEEN 0 AND 10000', 'student_discounts' => 'basis_points BETWEEN 0 AND 10000 AND ends_on >= starts_on', 'fee_invoices' => 'total_minor = gross_minor - discount_minor AND total_minor >= 0 AND discount_minor >= 0 AND due_on >= issued_on', 'invoice_lines' => 'amount_minor >= 0', 'payments' => 'amount_minor > 0', 'payment_allocations' => 'amount_minor > 0', 'payment_refunds' => 'amount_minor > 0', 'refund_allocations' => 'amount_minor > 0', 'expenses' => 'amount_minor > 0', 'staff_compensation' => 'base_salary_minor >= 0', 'payroll_items' => 'net_salary_minor = base_salary_minor + adjustment_minor AND net_salary_minor >= 0', 'journal_lines' => 'debit_minor >= 0 AND credit_minor >= 0 AND ((debit_minor > 0 AND credit_minor = 0) OR (credit_minor > 0 AND debit_minor = 0))'] as $table => $check) {
                DB::statement("ALTER TABLE $table ADD CONSTRAINT {$table}_financial_check CHECK ($check)");
            }
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION infotech_journal_balance() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE target uuid; d bigint; c bigint; n integer;
BEGIN
 IF TG_TABLE_NAME = 'journal_entries' THEN target := NEW.id; ELSE target := NEW.journal_entry_id; END IF;
 SELECT COALESCE(SUM(debit_minor),0),COALESCE(SUM(credit_minor),0),COUNT(*) INTO d,c,n FROM journal_lines WHERE journal_entry_id=target;
 IF n < 2 OR d <> c THEN RAISE EXCEPTION 'Unbalanced journal' USING ERRCODE='23514'; END IF;
 RETURN NULL;
END $$;
CREATE CONSTRAINT TRIGGER journal_entry_balance AFTER INSERT ON journal_entries DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION infotech_journal_balance();
CREATE CONSTRAINT TRIGGER journal_line_balance AFTER INSERT ON journal_lines DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION infotech_journal_balance();
CREATE OR REPLACE FUNCTION infotech_immutable_finance() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'Finalized financial records are immutable' USING ERRCODE='23514'; END $$;
SQL);
            foreach (['journal_entries', 'journal_lines', 'fee_invoices', 'invoice_lines', 'invoice_adjustments', 'payment_allocations', 'payment_refunds', 'refund_allocations', 'staff_compensation', 'payroll_adjustments', 'payroll_items'] as $table) {
                DB::statement("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON $table FOR EACH ROW EXECUTE FUNCTION infotech_immutable_finance()");
            }
            DB::statement("CREATE UNIQUE INDEX one_original_admission_invoice ON fee_invoices(student_id) WHERE kind='admission'");
        }
    }

    public function down(): void
    {
        foreach (['payroll_items', 'payroll_periods', 'payroll_adjustments', 'staff_compensation', 'expenses', 'expense_categories', 'refund_allocations', 'payment_refunds', 'payment_allocations', 'payments', 'payment_methods', 'invoice_adjustments', 'invoice_lines', 'fee_invoices', 'student_discounts', 'discount_policies', 'student_fee_assignments', 'fee_schedules', 'journal_lines', 'journal_entries', 'accounts', 'document_sequences'] as $t) {
            Schema::dropIfExists($t);
        }if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS infotech_journal_balance()');
            DB::statement('DROP FUNCTION IF EXISTS infotech_immutable_finance()');
        }
    }
};
