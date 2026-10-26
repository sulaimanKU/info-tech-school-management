<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_guardians', fn (Blueprint $t) => $t->foreignUuid('photo_file_id')->nullable()->constrained('private_files')->nullOnDelete());
        Schema::table('private_files', fn (Blueprint $t) => $t->foreignUuid('parent_guardian_id')->nullable()->constrained('parent_guardians')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('private_files', fn (Blueprint $t) => $t->dropConstrainedForeignId('parent_guardian_id'));
        Schema::table('parent_guardians', fn (Blueprint $t) => $t->dropConstrainedForeignId('photo_file_id'));
    }
};
