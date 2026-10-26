<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('student_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('guardian_user_id')->constrained('users')->restrictOnDelete();
            $t->string('number')->unique();
            $t->string('category', 40);
            $t->string('subject', 200);
            $t->string('status', 24)->default('submitted');
            $t->string('priority', 20)->default('normal');
            $t->foreignUuid('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->timestampTz('resolved_at')->nullable();
            $t->timestampTz('closed_at')->nullable();
            $t->timestampsTz();
            $t->index(['status', 'priority']);
        });
        Schema::create('support_messages', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('support_ticket_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('sender_user_id')->constrained('users')->restrictOnDelete();
            $t->text('body');
            $t->boolean('is_internal')->default(false);
            $t->uuid('client_id')->unique();
            $t->char('payload_hash', 64);
            $t->timestampsTz();
        });
        Schema::create('support_message_attachments', function (Blueprint $t) {
            $t->foreignUuid('support_message_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('private_file_id')->constrained()->restrictOnDelete();
            $t->primary(['support_message_id', 'private_file_id']);
        });
        Schema::create('support_ticket_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('support_ticket_id')->constrained()->restrictOnDelete();
            $t->string('from_status', 24)->nullable();
            $t->string('to_status', 24);
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
        });
        Schema::create('support_ticket_forwards', function (Blueprint $t) {
            $t->foreignUuid('support_ticket_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('forwarded_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->primary(['support_ticket_id', 'user_id']);
        });
        Schema::create('announcements', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('title', 200);
            $t->text('body');
            $t->string('kind', 30);
            $t->string('audience', 30);
            $t->foreignUuid('section_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignUuid('target_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('status', 20)->default('draft');
            $t->timestampTz('publish_at')->nullable();
            $t->timestampTz('expires_at')->nullable();
            $t->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignUuid('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
            $t->index(['status', 'publish_at']);
        });
        Schema::create('announcement_reads', function (Blueprint $t) {
            $t->foreignUuid('announcement_id')->constrained()->restrictOnDelete();
            $t->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $t->timestampTz('read_at');
            $t->primary(['announcement_id', 'user_id']);
        });
        Schema::create('school_notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $t->string('event_key', 200);
            $t->string('kind', 40);
            $t->string('target_type', 30);
            $t->uuid('target_id');
            $t->foreignUuid('student_id')->nullable()->constrained()->restrictOnDelete();
            $t->timestampTz('read_at')->nullable();
            $t->string('push_status', 20)->default('pending');
            $t->unsignedInteger('push_attempts')->default(0);
            $t->timestampTz('push_after')->nullable();
            $t->timestampsTz();
            $t->unique(['user_id', 'event_key']);
            $t->index(['push_status', 'push_after']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE support_tickets ADD CONSTRAINT support_status CHECK(status IN ('submitted','acknowledged','in_progress','awaiting_parent','escalated','resolved','closed'))");
            DB::statement('CREATE TRIGGER support_messages_immutable BEFORE UPDATE OR DELETE ON support_messages FOR EACH ROW EXECUTE FUNCTION infotech_immutable_finance()');
            DB::statement('CREATE TRIGGER support_events_immutable BEFORE UPDATE OR DELETE ON support_ticket_events FOR EACH ROW EXECUTE FUNCTION infotech_immutable_finance()');
        }
    }

    public function down(): void
    {
        foreach (['school_notifications', 'announcement_reads', 'announcements', 'support_ticket_forwards', 'support_ticket_events', 'support_message_attachments', 'support_messages', 'support_tickets'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
