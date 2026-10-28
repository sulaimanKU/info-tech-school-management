<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_locks', function (Blueprint $t) {
            $t->char('key', 64)->primary();
        });
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        // Older MariaDB configurations implicitly add ON UPDATE to the first TIMESTAMP.
        // None of the application's migrations requests automatic timestamp updates.
        $columns = DB::select("SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = 'timestamp' AND EXTRA LIKE '%on update%' AND (TABLE_NAME, COLUMN_NAME) IN (('authentication_sessions', 'expires_at'), ('parent_student_links', 'verified_at'), ('otp_challenges', 'expires_at'), ('announcement_reads', 'read_at'), ('import_batches', 'expires_at'), ('biometric_punches', 'punched_at'))");
        foreach ($columns as $column) {
            $table = str_replace('`', '``', $column->TABLE_NAME);
            $name = str_replace('`', '``', $column->COLUMN_NAME);
            $nullable = $column->IS_NULLABLE === 'YES' ? 'NULL DEFAULT NULL' : 'NOT NULL DEFAULT CURRENT_TIMESTAMP';
            DB::statement("ALTER TABLE `$table` MODIFY `$name` {$column->COLUMN_TYPE} $nullable");
        }
        // Forward migration covers existing installations as well as fresh databases.
        // Invalid legacy data must be reconciled explicitly; never silently discard it.
        DB::statement("ALTER TABLE `academic_sessions` ADD CONSTRAINT `academic_dates` CHECK (ends_on >= starts_on)");
        DB::statement("ALTER TABLE `classes` ADD CONSTRAINT `class_order` CHECK (sort_order >= 0)");
        DB::statement("ALTER TABLE `authentication_sessions` ADD CONSTRAINT `session_expiry` CHECK (expires_at > created_at)");
        DB::statement("ALTER TABLE `students` ADD CONSTRAINT `student_status` CHECK (status IN ('active','withdrawn','transferred'))");
        DB::statement("ALTER TABLE `students` ADD CONSTRAINT `student_gender` CHECK (gender IN ('male','female','other'))");
        DB::statement("ALTER TABLE `students` ADD CONSTRAINT `student_birth` CHECK (date_of_birth <= admitted_on)");
        DB::statement("ALTER TABLE `enrollments` ADD CONSTRAINT `enrollment_dates` CHECK (ends_on IS NULL OR ends_on >= starts_on)");
        DB::statement("ALTER TABLE `attendance_records` ADD CONSTRAINT `attendance_status` CHECK (status IN ('present','absent','late','excused','school_holiday'))");
        DB::statement("ALTER TABLE `attendance_registers` ADD CONSTRAINT `register_status` CHECK (status IN ('draft','submitted'))");
        DB::statement("ALTER TABLE `grading_rules` ADD CONSTRAINT `grading_percent` CHECK (minimum_percent BETWEEN 0 AND 100)");
        DB::statement("ALTER TABLE `assessments` ADD CONSTRAINT `max_marks_positive` CHECK (maximum_marks > 0)");
        DB::statement("ALTER TABLE `assessment_results` ADD CONSTRAINT `result_marks_valid` CHECK (obtained_marks >= 0 AND obtained_marks <= maximum_marks AND percentage BETWEEN 0 AND 100)");
        DB::statement("ALTER TABLE `coordination_records` ADD CONSTRAINT `syllabus_percent` CHECK (progress_percent IS NULL OR progress_percent BETWEEN 0 AND 100)");
        DB::statement("ALTER TABLE `support_tickets` ADD CONSTRAINT `support_status` CHECK (status IN ('submitted','acknowledged','in_progress','awaiting_parent','escalated','resolved','closed'))");
        DB::statement("ALTER TABLE `coordinator_assignments` ADD CONSTRAINT `coordinator_assignment_dates` CHECK (ends_on IS NULL OR starts_on IS NULL OR ends_on >= starts_on)");
        DB::statement("ALTER TABLE `teacher_assignments` ADD CONSTRAINT `teacher_assignment_dates` CHECK (ends_on IS NULL OR starts_on IS NULL OR ends_on >= starts_on)");
        DB::statement("ALTER TABLE `fee_schedules` ADD CONSTRAINT `fee_schedules_financial_check` CHECK (amount_minor >= 0)");
        DB::statement("ALTER TABLE `student_fee_assignments` ADD CONSTRAINT `student_fee_assignments_financial_check` CHECK (tuition_override_minor IS NULL OR tuition_override_minor >= 0)");
        DB::statement("ALTER TABLE `discount_policies` ADD CONSTRAINT `discount_policies_financial_check` CHECK (maximum_basis_points BETWEEN 0 AND 10000)");
        DB::statement("ALTER TABLE `student_discounts` ADD CONSTRAINT `student_discounts_financial_check` CHECK (basis_points BETWEEN 0 AND 10000 AND ends_on >= starts_on)");
        DB::statement("ALTER TABLE `fee_invoices` ADD CONSTRAINT `fee_invoices_financial_check` CHECK (total_minor = gross_minor - discount_minor AND total_minor >= 0 AND discount_minor >= 0 AND due_on >= issued_on)");
        DB::statement("ALTER TABLE `invoice_lines` ADD CONSTRAINT `invoice_lines_financial_check` CHECK (amount_minor >= 0)");
        DB::statement("ALTER TABLE `payments` ADD CONSTRAINT `payments_financial_check` CHECK (amount_minor > 0)");
        DB::statement("ALTER TABLE `payment_allocations` ADD CONSTRAINT `payment_allocations_financial_check` CHECK (amount_minor > 0)");
        DB::statement("ALTER TABLE `payment_refunds` ADD CONSTRAINT `payment_refunds_financial_check` CHECK (amount_minor > 0)");
        DB::statement("ALTER TABLE `refund_allocations` ADD CONSTRAINT `refund_allocations_financial_check` CHECK (amount_minor > 0)");
        DB::statement("ALTER TABLE `expenses` ADD CONSTRAINT `expenses_financial_check` CHECK (amount_minor > 0)");
        DB::statement("ALTER TABLE `staff_compensation` ADD CONSTRAINT `staff_compensation_financial_check` CHECK (base_salary_minor >= 0)");
        DB::statement("ALTER TABLE `payroll_items` ADD CONSTRAINT `payroll_items_financial_check` CHECK (net_salary_minor = base_salary_minor + adjustment_minor AND net_salary_minor >= 0)");
        DB::statement("ALTER TABLE `journal_lines` ADD CONSTRAINT `journal_lines_financial_check` CHECK (debit_minor >= 0 AND credit_minor >= 0 AND ((debit_minor > 0 AND credit_minor = 0) OR (credit_minor > 0 AND debit_minor = 0)))");

        // Invisible virtual columns keep internal uniqueness keys out of API SELECT * results.
        // RTRIM makes CHAR-derived expressions independent of PAD_CHAR_TO_FULL_LENGTH.
        DB::statement("ALTER TABLE `enrollments` ADD COLUMN `active_student_key` CHAR(36) GENERATED ALWAYS AS (CASE WHEN status = 'active' THEN RTRIM(student_id) END) VIRTUAL INVISIBLE, ADD UNIQUE INDEX `enrollment_active_student` (`active_student_key`)");
        DB::statement("ALTER TABLE `enrollments` ADD COLUMN `active_roll_key` CHAR(36) GENERATED ALWAYS AS (CASE WHEN status = 'active' THEN RTRIM(section_id) END) VIRTUAL INVISIBLE, ADD UNIQUE INDEX `enrollment_active_roll` (`active_roll_key`, `roll_number`)");
        DB::statement("ALTER TABLE `parent_student_links` ADD COLUMN `primary_guardian_key` CHAR(36) GENERATED ALWAYS AS (CASE WHEN is_primary = 1 AND is_active = 1 THEN RTRIM(student_id) END) VIRTUAL INVISIBLE, ADD UNIQUE INDEX `primary_guardian_unique` (`primary_guardian_key`)");
        DB::statement("ALTER TABLE `teacher_assignments` ADD COLUMN `class_teacher_key` CHAR(36) GENERATED ALWAYS AS (CASE WHEN is_class_teacher = 1 AND is_active = 1 THEN RTRIM(section_id) END) VIRTUAL INVISIBLE, ADD UNIQUE INDEX `class_teacher_unique` (`class_teacher_key`)");
        DB::statement("ALTER TABLE `teacher_assignments` ADD COLUMN `active_subject_key` CHAR(36) GENERATED ALWAYS AS (CASE WHEN is_active = 1 THEN RTRIM(subject_id) END) VIRTUAL INVISIBLE, ADD UNIQUE INDEX `subject_teacher_unique` (`staff_profile_id`, `section_id`, `active_subject_key`)");
        DB::statement("ALTER TABLE `coordinator_assignments` ADD COLUMN `active_coordinator_key` CHAR(36) GENERATED ALWAYS AS (CASE WHEN is_active = 1 THEN RTRIM(section_id) END) VIRTUAL INVISIBLE, ADD UNIQUE INDEX `active_coordinator_section` (`staff_profile_id`, `active_coordinator_key`)");
        DB::statement("ALTER TABLE `fee_invoices` ADD COLUMN `admission_student_key` CHAR(36) GENERATED ALWAYS AS (CASE WHEN kind = 'admission' THEN RTRIM(student_id) END) VIRTUAL INVISIBLE, ADD UNIQUE INDEX `one_original_admission_invoice` (`admission_student_key`)");

        DB::unprepared("CREATE TRIGGER `journal_entries_immutable_update` BEFORE UPDATE ON `journal_entries` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `journal_entries_immutable_delete` BEFORE DELETE ON `journal_entries` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `journal_lines_immutable_update` BEFORE UPDATE ON `journal_lines` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `journal_lines_immutable_delete` BEFORE DELETE ON `journal_lines` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `fee_invoices_immutable_update` BEFORE UPDATE ON `fee_invoices` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `fee_invoices_immutable_delete` BEFORE DELETE ON `fee_invoices` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `invoice_lines_immutable_update` BEFORE UPDATE ON `invoice_lines` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `invoice_lines_immutable_delete` BEFORE DELETE ON `invoice_lines` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `invoice_adjustments_immutable_update` BEFORE UPDATE ON `invoice_adjustments` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `invoice_adjustments_immutable_delete` BEFORE DELETE ON `invoice_adjustments` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `payment_allocations_immutable_update` BEFORE UPDATE ON `payment_allocations` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `payment_allocations_immutable_delete` BEFORE DELETE ON `payment_allocations` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `payment_refunds_immutable_update` BEFORE UPDATE ON `payment_refunds` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `payment_refunds_immutable_delete` BEFORE DELETE ON `payment_refunds` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `refund_allocations_immutable_update` BEFORE UPDATE ON `refund_allocations` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `refund_allocations_immutable_delete` BEFORE DELETE ON `refund_allocations` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `staff_compensation_immutable_update` BEFORE UPDATE ON `staff_compensation` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `staff_compensation_immutable_delete` BEFORE DELETE ON `staff_compensation` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `payroll_adjustments_immutable_update` BEFORE UPDATE ON `payroll_adjustments` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `payroll_adjustments_immutable_delete` BEFORE DELETE ON `payroll_adjustments` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `payroll_items_immutable_update` BEFORE UPDATE ON `payroll_items` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `payroll_items_immutable_delete` BEFORE DELETE ON `payroll_items` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `support_messages_immutable_update` BEFORE UPDATE ON `support_messages` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `support_messages_immutable_delete` BEFORE DELETE ON `support_messages` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `support_ticket_events_immutable_update` BEFORE UPDATE ON `support_ticket_events` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");
        DB::unprepared("CREATE TRIGGER `support_ticket_events_immutable_delete` BEFORE DELETE ON `support_ticket_events` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Finalized records are immutable'");

        // MariaDB cannot defer a cross-row balance constraint until commit.
        // JournalService validates the complete entry before inserting it atomically.
        DB::unprepared(<<<'SQL'
CREATE TRIGGER payments_guard_update BEFORE UPDATE ON payments FOR EACH ROW
BEGIN
 IF OLD.status = 'verified' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Verified payment is immutable'; END IF;
 IF NOT (NEW.id <=> OLD.id) OR NOT (NEW.payment_method_id <=> OLD.payment_method_id)
 OR NOT (NEW.paid_on <=> OLD.paid_on) OR NOT (NEW.amount_minor <=> OLD.amount_minor)
 OR NOT (NEW.reference <=> OLD.reference) OR NOT (NEW.proof_file_id <=> OLD.proof_file_id)
 OR NOT (NEW.request_id <=> OLD.request_id) OR NOT (NEW.payload_hash <=> OLD.payload_hash)
 OR NOT (NEW.recorded_by <=> OLD.recorded_by) OR NOT (NEW.created_at <=> OLD.created_at)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment values are immutable; cancel and recreate'; END IF;
END
SQL);
        DB::unprepared("CREATE TRIGGER payments_guard_delete BEFORE DELETE ON payments FOR EACH ROW BEGIN IF OLD.status = 'verified' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Verified payment is immutable'; END IF; END");
        DB::unprepared("CREATE TRIGGER expenses_guard_insert BEFORE INSERT ON expenses FOR EACH ROW BEGIN IF NEW.status = 'paid' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Record request before payment'; END IF; END");
        DB::unprepared(<<<'SQL'
CREATE TRIGGER expenses_guard_update BEFORE UPDATE ON expenses FOR EACH ROW
BEGIN
 IF OLD.status = 'paid' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Paid expense is immutable'; END IF;
 IF NOT (NEW.amount_minor <=> OLD.amount_minor) OR NOT (NEW.expense_category_id <=> OLD.expense_category_id)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Expense request values are immutable'; END IF;
 IF NEW.status = 'paid' AND NEW.amount_minor > 50000 AND (OLD.status <> 'approved' OR OLD.approved_at IS NULL OR NEW.paid_on < DATE(OLD.approved_at))
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Prior management approval required above PKR 500'; END IF;
END
SQL);
        DB::unprepared(<<<'SQL'
CREATE TRIGGER payroll_guard_update BEFORE UPDATE ON payroll_periods FOR EACH ROW
BEGIN
 IF OLD.status = 'paid' OR NOT (NEW.total_minor <=> OLD.total_minor) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Final payroll values are immutable'; END IF;
 IF NEW.status = 'paid' AND (OLD.status <> 'approved' OR OLD.approved_by IS NULL)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payroll must be approved before disbursement'; END IF;
END
SQL);
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("DROP TRIGGER IF EXISTS `journal_entries_immutable_update`");
            DB::statement("DROP TRIGGER IF EXISTS `journal_entries_immutable_delete`");
            DB::statement("DROP TRIGGER IF EXISTS `journal_lines_immutable_update`");
            DB::statement("DROP TRIGGER IF EXISTS `journal_lines_immutable_delete`");
            DB::statement("DROP TRIGGER IF EXISTS `fee_invoices_immutable_update`");
            DB::statement("DROP TRIGGER IF EXISTS `fee_invoices_immutable_delete`");
            DB::statement("DROP TRIGGER IF EXISTS `invoice_lines_immutable_update`");
            DB::statement("DROP TRIGGER IF EXISTS `invoice_lines_immutable_delete`");
            DB::statement("DROP TRIGGER IF EXISTS `invoice_adjustments_immutable_update`");
            DB::statement("DROP TRIGGER IF EXISTS `invoice_adjustments_immutable_delete`");
            DB::statement("DROP TRIGGER IF EXISTS `payment_allocations_immutable_update`");
            DB::statement("DROP TRIGGER IF EXISTS `payment_allocations_immutable_delete`");
            DB::statement("DROP TRIGGER IF EXISTS `payment_refunds_immutable_update`");
            DB::statement("DROP TRIGGER IF EXISTS `payment_refunds_immutable_delete`");
            DB::statement("DROP TRIGGER IF EXISTS `refund_allocations_immutable_update`");
            DB::statement("DROP TRIGGER IF EXISTS `refund_allocations_immutable_delete`");
            DB::statement("DROP TRIGGER IF EXISTS `staff_compensation_immutable_update`");
            DB::statement("DROP TRIGGER IF EXISTS `staff_compensation_immutable_delete`");
            DB::statement("DROP TRIGGER IF EXISTS `payroll_adjustments_immutable_update`");
            DB::statement("DROP TRIGGER IF EXISTS `payroll_adjustments_immutable_delete`");
            DB::statement("DROP TRIGGER IF EXISTS `payroll_items_immutable_update`");
            DB::statement("DROP TRIGGER IF EXISTS `payroll_items_immutable_delete`");
            DB::statement("DROP TRIGGER IF EXISTS `support_messages_immutable_update`");
            DB::statement("DROP TRIGGER IF EXISTS `support_messages_immutable_delete`");
            DB::statement("DROP TRIGGER IF EXISTS `support_ticket_events_immutable_update`");
            DB::statement("DROP TRIGGER IF EXISTS `support_ticket_events_immutable_delete`");
            DB::statement("DROP TRIGGER IF EXISTS `payments_guard_update`");
            DB::statement("DROP TRIGGER IF EXISTS `payments_guard_delete`");
            DB::statement("DROP TRIGGER IF EXISTS `expenses_guard_insert`");
            DB::statement("DROP TRIGGER IF EXISTS `expenses_guard_update`");
            DB::statement("DROP TRIGGER IF EXISTS `payroll_guard_update`");
            DB::statement("ALTER TABLE `fee_invoices` DROP INDEX `one_original_admission_invoice`, DROP COLUMN `admission_student_key`");
            DB::statement("ALTER TABLE `coordinator_assignments` DROP INDEX `active_coordinator_section`, DROP COLUMN `active_coordinator_key`");
            DB::statement("ALTER TABLE `teacher_assignments` DROP INDEX `subject_teacher_unique`, DROP COLUMN `active_subject_key`");
            DB::statement("ALTER TABLE `teacher_assignments` DROP INDEX `class_teacher_unique`, DROP COLUMN `class_teacher_key`");
            DB::statement("ALTER TABLE `parent_student_links` DROP INDEX `primary_guardian_unique`, DROP COLUMN `primary_guardian_key`");
            DB::statement("ALTER TABLE `enrollments` DROP INDEX `enrollment_active_roll`, DROP COLUMN `active_roll_key`");
            DB::statement("ALTER TABLE `enrollments` DROP INDEX `enrollment_active_student`, DROP COLUMN `active_student_key`");
            DB::statement("ALTER TABLE `journal_lines` DROP CONSTRAINT `journal_lines_financial_check`");
            DB::statement("ALTER TABLE `payroll_items` DROP CONSTRAINT `payroll_items_financial_check`");
            DB::statement("ALTER TABLE `staff_compensation` DROP CONSTRAINT `staff_compensation_financial_check`");
            DB::statement("ALTER TABLE `expenses` DROP CONSTRAINT `expenses_financial_check`");
            DB::statement("ALTER TABLE `refund_allocations` DROP CONSTRAINT `refund_allocations_financial_check`");
            DB::statement("ALTER TABLE `payment_refunds` DROP CONSTRAINT `payment_refunds_financial_check`");
            DB::statement("ALTER TABLE `payment_allocations` DROP CONSTRAINT `payment_allocations_financial_check`");
            DB::statement("ALTER TABLE `payments` DROP CONSTRAINT `payments_financial_check`");
            DB::statement("ALTER TABLE `invoice_lines` DROP CONSTRAINT `invoice_lines_financial_check`");
            DB::statement("ALTER TABLE `fee_invoices` DROP CONSTRAINT `fee_invoices_financial_check`");
            DB::statement("ALTER TABLE `student_discounts` DROP CONSTRAINT `student_discounts_financial_check`");
            DB::statement("ALTER TABLE `discount_policies` DROP CONSTRAINT `discount_policies_financial_check`");
            DB::statement("ALTER TABLE `student_fee_assignments` DROP CONSTRAINT `student_fee_assignments_financial_check`");
            DB::statement("ALTER TABLE `fee_schedules` DROP CONSTRAINT `fee_schedules_financial_check`");
            DB::statement("ALTER TABLE `teacher_assignments` DROP CONSTRAINT `teacher_assignment_dates`");
            DB::statement("ALTER TABLE `coordinator_assignments` DROP CONSTRAINT `coordinator_assignment_dates`");
            DB::statement("ALTER TABLE `support_tickets` DROP CONSTRAINT `support_status`");
            DB::statement("ALTER TABLE `coordination_records` DROP CONSTRAINT `syllabus_percent`");
            DB::statement("ALTER TABLE `assessment_results` DROP CONSTRAINT `result_marks_valid`");
            DB::statement("ALTER TABLE `assessments` DROP CONSTRAINT `max_marks_positive`");
            DB::statement("ALTER TABLE `grading_rules` DROP CONSTRAINT `grading_percent`");
            DB::statement("ALTER TABLE `attendance_registers` DROP CONSTRAINT `register_status`");
            DB::statement("ALTER TABLE `attendance_records` DROP CONSTRAINT `attendance_status`");
            DB::statement("ALTER TABLE `enrollments` DROP CONSTRAINT `enrollment_dates`");
            DB::statement("ALTER TABLE `students` DROP CONSTRAINT `student_status`");
            DB::statement("ALTER TABLE `students` DROP CONSTRAINT `student_gender`");
            DB::statement("ALTER TABLE `students` DROP CONSTRAINT `student_birth`");
            DB::statement("ALTER TABLE `authentication_sessions` DROP CONSTRAINT `session_expiry`");
            DB::statement("ALTER TABLE `classes` DROP CONSTRAINT `class_order`");
            DB::statement("ALTER TABLE `academic_sessions` DROP CONSTRAINT `academic_dates`");
        }
        Schema::dropIfExists('transaction_locks');
    }
};
