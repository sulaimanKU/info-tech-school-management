<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_requests', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('student_id')->unique()->constrained()->restrictOnDelete();
            $t->char('payload_hash', 64);
            $t->timestampsTz();
        });
        Schema::create('opening_balance_coverage', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('fee_invoice_id')->unique()->constrained()->restrictOnDelete();
            $t->char('from_period', 7);
            $t->char('through_period', 7);
            $t->foreignUuid('approved_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
        });
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE opening_balance_coverage ADD CONSTRAINT opening_coverage_range_check CHECK (from_period <= through_period)');
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $suffix = strtolower($operation);
                DB::unprepared("CREATE TRIGGER opening_coverage_immutable_$suffix BEFORE $operation ON opening_balance_coverage FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Approved opening coverage is immutable'");
            }
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE TRIGGER opening_coverage_immutable BEFORE UPDATE OR DELETE ON opening_balance_coverage FOR EACH ROW EXECUTE FUNCTION infotech_immutable_finance()');
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Admission idempotency history must be preserved. Restore a coordinated backup to reverse this release.');
    }
};
