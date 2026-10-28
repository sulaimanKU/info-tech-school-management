<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_guardians', function (Blueprint $t) {
            $t->char('cnic', 13)->nullable()->unique();
            $t->string('mobile', 13)->nullable();
            $t->text('cnic_ciphertext')->nullable()->change();
            $t->char('cnic_lookup', 64)->nullable()->change();
            $t->char('cnic_last4', 4)->nullable()->change();
            $t->text('mobile_ciphertext')->nullable()->change();
        });
        Schema::table('import_batches', fn (Blueprint $t) => $t->longText('source_rows')->nullable());
    }

    public function down(): void
    {
        throw new RuntimeException('Readable data conversion cannot be reversed by rollback. Restore a verified backup with its original APP_KEY and release.');
    }
};
