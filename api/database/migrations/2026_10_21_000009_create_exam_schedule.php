<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_schedules', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('section_id')->constrained();
            $t->foreignUuid('subject_id')->constrained();
            $t->string('title', 200);
            $t->date('date');
            $t->time('starts_at');
            $t->time('ends_at');
            $t->string('room', 100)->nullable();
            $t->string('status', 16)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->foreignUuid('created_by')->constrained('users');
            $t->timestampsTz();
            $t->unique(['section_id', 'date', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_schedules');
    }
};
