<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('mobile', 30)->nullable();
            $t->text('address')->nullable();
            $t->foreignUuid('photo_file_id')->nullable()->constrained('private_files')->restrictOnDelete();
        });
        Schema::table('private_files', function (Blueprint $t) {
            $t->foreignUuid('owner_user_id')->nullable()->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('private_files', function (Blueprint $t) {
            $t->dropConstrainedForeignId('owner_user_id');
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('photo_file_id');
            $t->dropColumn(['mobile', 'address']);
        });
    }
};
