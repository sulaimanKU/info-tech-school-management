<?php

use App\Services\BusinessCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nullable additions permit a safe resumable backfill on MariaDB's nontransactional DDL.
        foreach (['users' => 'account_code', 'admission_requests' => 'admission_number'] as $table => $column) {
            if (! Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->string($column, 32)->nullable()->unique());
            }
            DB::table($table)->whereNull($column)->orderBy('id')->chunkById(200, function ($rows) use ($table, $column) {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($table, $column, $row) {
                        $record = DB::table($table)->where('id', $row->id)->lockForUpdate()->first();
                        if ($record->$column === null) {
                            DB::table($table)->where('id', $row->id)->update([$column => app(BusinessCode::class)->next($table === 'users' ? 'ACC' : 'ADM')]);
                        }
                    });
                }
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Business identifiers must be preserved. Restore a reviewed backup instead.');
    }
};
