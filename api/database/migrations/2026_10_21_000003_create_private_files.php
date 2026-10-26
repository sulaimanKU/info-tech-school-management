<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_files', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('student_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignUuid('section_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('path')->unique();
            $t->string('mime', 100);
            $t->string('purpose', 32);
            $t->unsignedBigInteger('size');
            $t->char('sha256', 64);
            $t->timestampsTz();
        });
        Schema::table('students', fn (Blueprint $t) => $t->foreignUuid('photo_file_id')->nullable()->constrained('private_files')->restrictOnDelete());
    }

    public function down(): void
    {
        Schema::table('students', fn (Blueprint $t) => $t->dropConstrainedForeignId('photo_file_id'));
        Schema::dropIfExists('private_files');
    }
};
