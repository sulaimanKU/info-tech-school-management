<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION infotech_guard_payment() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF OLD.status='verified' THEN RAISE EXCEPTION 'Verified payment is immutable' USING ERRCODE='23514'; END IF;
 IF TG_OP='UPDATE' AND (to_jsonb(OLD)-ARRAY['status','receipt_number','verified_by','verified_at','updated_at']) <> (to_jsonb(NEW)-ARRAY['status','receipt_number','verified_by','verified_at','updated_at']) THEN RAISE EXCEPTION 'Payment values are immutable; cancel and recreate' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER payments_guard BEFORE UPDATE OR DELETE ON payments FOR EACH ROW EXECUTE FUNCTION infotech_guard_payment();
CREATE OR REPLACE FUNCTION infotech_guard_expense() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_OP='INSERT' THEN IF NEW.status='paid' THEN RAISE EXCEPTION 'Record request before payment' USING ERRCODE='23514'; END IF; RETURN NEW; END IF;
 IF OLD.status='paid' THEN RAISE EXCEPTION 'Paid expense is immutable' USING ERRCODE='23514'; END IF;
 IF NEW.amount_minor<>OLD.amount_minor OR NEW.expense_category_id<>OLD.expense_category_id THEN RAISE EXCEPTION 'Expense request values are immutable' USING ERRCODE='23514'; END IF;
 IF NEW.status='paid' AND NEW.amount_minor>50000 AND (OLD.status<>'approved' OR OLD.approved_at IS NULL OR NEW.paid_on<OLD.approved_at::date) THEN RAISE EXCEPTION 'Prior management approval required above PKR 500' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER expenses_guard BEFORE INSERT OR UPDATE ON expenses FOR EACH ROW EXECUTE FUNCTION infotech_guard_expense();
CREATE OR REPLACE FUNCTION infotech_guard_payroll() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF OLD.status='paid' OR NEW.total_minor<>OLD.total_minor THEN RAISE EXCEPTION 'Final payroll values are immutable' USING ERRCODE='23514'; END IF;
 IF NEW.status='paid' AND (OLD.status<>'approved' OR OLD.approved_by IS NULL) THEN RAISE EXCEPTION 'Payroll must be approved before disbursement' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER payroll_guard BEFORE UPDATE ON payroll_periods FOR EACH ROW EXECUTE FUNCTION infotech_guard_payroll();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }DB::statement('DROP TRIGGER IF EXISTS payments_guard ON payments');
        DB::statement('DROP TRIGGER IF EXISTS expenses_guard ON expenses');
        DB::statement('DROP TRIGGER IF EXISTS payroll_guard ON payroll_periods');
        foreach (['infotech_guard_payment', 'infotech_guard_expense', 'infotech_guard_payroll'] as $function) {
            DB::statement("DROP FUNCTION IF EXISTS $function()");
        }
    }
};
