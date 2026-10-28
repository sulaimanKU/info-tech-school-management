<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_payments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('payroll_period_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('payment_method_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();
            $t->uuid('request_id')->nullable()->unique();
            $t->char('payload_hash', 64);
            $t->bigInteger('amount_minor');
            $t->date('paid_on');
            $t->string('reference', 200)->nullable();
            $t->string('notes', 500)->nullable();
            $t->timestampsTz();
        });
        Schema::create('payroll_payment_allocations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('payroll_payment_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('payroll_item_id')->constrained()->restrictOnDelete();
            $t->bigInteger('amount_minor');
            $t->timestampsTz();
            $t->unique(['payroll_payment_id', 'payroll_item_id'], 'payroll_payment_item_uq');
        });
        foreach (['payroll_payments', 'payroll_payment_allocations'] as $table) {
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `{$table}_amount_check` CHECK (amount_minor > 0)");
                foreach (['UPDATE', 'DELETE'] as $op) {
                    $suffix = strtolower($op);
                    DB::unprepared("CREATE TRIGGER `{$table}_immutable_{$suffix}` BEFORE $op ON `$table` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payroll payment history is immutable'");
                }
            } elseif (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE $table ADD CONSTRAINT {$table}_amount_check CHECK (amount_minor > 0)");
                DB::statement("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON $table FOR EACH ROW EXECUTE FUNCTION infotech_immutable_finance()");
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Payroll history must be preserved. Restore a coordinated backup to reverse this release.');
    }
};
