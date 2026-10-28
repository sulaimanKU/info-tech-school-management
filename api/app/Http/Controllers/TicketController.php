<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AcademicFilters;
use App\Services\Finance\DocumentNumber;
use App\Services\NotificationService;
use App\Services\SchoolAccess;
use App\Services\TicketAccess;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    public function __construct(private SchoolAccess $school, private TicketAccess $access, private NotificationService $notifications) {}

    public function index(Request $r)
    {
        $v = app(AcademicFilters::class)->values($r, ['assigned_to' => 'nullable|uuid|exists:users,id', 'status' => 'nullable|in:submitted,acknowledged,in_progress,awaiting_parent,escalated,resolved,closed']);
        $q = $this->access->query($r->user());
        $students = app(AcademicFilters::class)->students($this->school->students($r->user()), $r, array_diff_key($v, array_flip(['status', 'search'])));
        $q->whereIn('t.student_id', $students->select('students.id'));
        if (! empty($v['search'])) {
            $q->where(function ($q) use ($v) {
                $search = '%'.$v['search'].'%';
                $q->where('t.subject', 'like', $search)->orWhere('t.number', 'like', $search)->orWhereIn('t.student_id', DB::table('students')->where('full_name', 'like', $search)->orWhere('registration_number', 'like', $search)->select('id'))->orWhereIn('t.guardian_user_id', DB::table('parent_guardians')->where('full_name', 'like', $search)->select('user_id'));
            });
        }
        if (isset($v['student_id'])) {
            $q->where('t.student_id', $v['student_id']);
        }
        if (isset($v['assigned_to'])) {
            $q->where('t.assigned_to', $v['assigned_to']);
        }
        if (isset($v['from'])) {
            $q->whereDate('t.created_at', '>=', $v['from']);
        }if (isset($v['to'])) {
            $q->whereDate('t.created_at', '<=', $v['to']);
        }
        if ($status = $r->query('status')) {
            $q->where('t.status', $status);
        }

        return Domain::response($q->join('students as s', 's.id', '=', 't.student_id')->orderByRaw("CASE WHEN t.priority='urgent' THEN 0 ELSE 1 END")->orderByDesc('t.updated_at')->paginate(30, ['t.*', 's.full_name as student_name']));
    }

    public function show(Request $r, string $ticket)
    {
        $t = $this->access->ticket($r->user(), $ticket);
        $messages = DB::table('support_messages as m')->join('users as u', 'u.id', '=', 'm.sender_user_id')->where('m.support_ticket_id', $ticket);
        if ($this->school->isParent($r->user())) {
            $messages->where('m.is_internal', false);
        }
        $items = $messages->orderBy('m.created_at')->get(['m.id', 'm.body', 'm.is_internal', 'm.created_at', 'm.sender_user_id', 'u.name as sender_name'])->map(function ($m) use ($t) {
            $senderKind = $m->sender_user_id === $t->guardian_user_id ? 'parent' : 'staff';
            unset($m->sender_user_id);

            return [...(array) $m, 'sender_kind' => $senderKind, 'attachments' => DB::table('support_message_attachments as a')->join('private_files as f', 'f.id', '=', 'a.private_file_id')->where('a.support_message_id', $m->id)->get(['f.id', 'f.mime', 'f.size'])];
        });

        return Domain::response([...(array) $t, 'messages' => $items, 'forwarded_to' => $r->user()->hasPermission('communication.manage') ? DB::table('support_ticket_forwards')->where('support_ticket_id', $ticket)->pluck('user_id') : [], 'events' => DB::table('support_ticket_events')->where('support_ticket_id', $ticket)->orderBy('created_at')->get(['from_status', 'to_status', 'created_at'])]);
    }

    public function store(Request $r)
    {
        abort_unless($this->school->isParent($r->user()), 403);
        $v = $r->validate(['student_id' => 'required|uuid|exists:students,id', 'category' => 'required|in:academic_progress,homework,attendance,teacher_concern,student_behavior,examination,general,other', 'subject' => 'required|string|max:200', 'message' => 'required|string|max:5000', 'attachments' => 'present|array|max:5', 'attachments.*' => 'uuid|distinct|exists:private_files,id', 'client_id' => 'required|uuid']);
        $this->school->student($r->user(), $v['student_id']);
        $id = DB::transaction(function () use ($r, $v) {
            $old = DB::table('support_messages')->where('client_id', $v['client_id'])->first();
            if ($old) {
                abort_unless($old->sender_user_id === $r->user()->id, 403);
                abort_unless(hash_equals($old->payload_hash, hash('sha256', json_encode([$v['message'], false, $v['attachments']]))), 409);

                $existing = DB::table('support_tickets')->find($old->support_ticket_id);
                abort_unless($existing->student_id === $v['student_id'] && $existing->subject === $v['subject'] && $existing->category === $v['category'], 409);

                return $old->support_ticket_id;
            }
            $id = Domain::insert('support_tickets', ['student_id' => $v['student_id'], 'guardian_user_id' => $r->user()->id, 'number' => app(DocumentNumber::class)->next('Q', now()->toDateString()), 'category' => $v['category'], 'subject' => $v['subject'], 'status' => 'submitted']);
            $this->message($r, $id, $v['message'], false, $v['attachments'], $v['client_id']);
            Domain::insert('support_ticket_events', ['support_ticket_id' => $id, 'from_status' => null, 'to_status' => 'submitted', 'created_by' => $r->user()->id]);
            Audit::record('support_ticket_submitted', $r->user()->id, 'support_tickets', $id);

            return $id;
        });

        return Domain::response(['id' => $id], 201);
    }

    private function message(Request $r, string $ticket, string $body, bool $internal, array $files, string $client): string
    {
        $t = DB::table('support_tickets')->find($ticket);
        $hash = hash('sha256', json_encode([$body, $internal, $files]));
        $old = DB::table('support_messages')->where('client_id', $client)->first();
        if ($old) {
            abort_unless($old->support_ticket_id === $ticket && $old->sender_user_id === $r->user()->id && hash_equals($old->payload_hash, $hash), 409);

            return $old->id;
        }
        foreach ($files as $file) {
            $f = DB::table('private_files')->where('id', $file)->firstOrFail();
            abort_unless($f->student_id === $t->student_id && $f->purpose === 'message' && $f->uploaded_by === $r->user()->id, 403);
        }
        $id = Domain::insert('support_messages', ['support_ticket_id' => $ticket, 'sender_user_id' => $r->user()->id, 'body' => $body, 'is_internal' => $internal, 'client_id' => $client, 'payload_hash' => $hash]);
        foreach ($files as $f) {
            DB::table('support_message_attachments')->insert(['support_message_id' => $id, 'private_file_id' => $f]);
        }Audit::record($internal ? 'support_internal_note' : 'support_message_sent', $r->user()->id, 'support_messages', $id);
        if (! $internal && $r->user()->id !== $t->guardian_user_id) {
            $this->notifications->user($t->guardian_user_id, 'coordinator_response', 'ticket', $ticket, 'ticket-message:'.$id, $t->student_id);
        }

        return $id;
    }

    public function reply(Request $r, string $ticket)
    {
        $t = $this->access->ticket($r->user(), $ticket);
        $v = $r->validate(['message' => 'required|string|max:5000', 'is_internal' => 'required|boolean', 'attachments' => 'present|array|max:5', 'attachments.*' => 'uuid|distinct|exists:private_files,id', 'client_id' => 'required|uuid']);
        $parent = $this->school->isParent($r->user());
        abort_if($parent && $v['is_internal'], 403);
        if (! $parent && ! $r->user()->hasPermission('communication.manage')) {
            abort_unless($v['is_internal'], 403);
        }
        $id = DB::transaction(function () use ($r, $ticket, $v, $parent) {
            $t = DB::table('support_tickets')->where('id', $ticket)->lockForUpdate()->firstOrFail();
            if ($t->status === 'closed') {
                Domain::fail('ticket', 'Create a new ticket for a closed conversation.');
            }$replay = DB::table('support_messages')->where('client_id', $v['client_id'])->exists();
            $id = $this->message($r, $ticket, $v['message'], $v['is_internal'], $v['attachments'], $v['client_id']);
            if ($replay) {
                return $id;
            }
            $status = $parent && in_array($t->status, ['awaiting_parent', 'resolved']) ? 'in_progress' : $t->status;
            DB::table('support_tickets')->where('id', $ticket)->update(['status' => $status, 'version' => $t->version + 1, 'updated_at' => now()]);
            if ($status !== $t->status) {
                Domain::insert('support_ticket_events', ['support_ticket_id' => $ticket, 'from_status' => $t->status, 'to_status' => $status, 'created_by' => $r->user()->id]);
            }

            return $id;
        });

        return Domain::response(['id' => $id], 201);
    }

    public function workflow(Request $r, string $ticket)
    {
        $this->school->require($r->user(), 'communication.manage');
        $this->access->ticket($r->user(), $ticket);
        $v = $r->validate(['version' => 'required|integer|min:1', 'status' => 'required|in:submitted,acknowledged,in_progress,awaiting_parent,escalated,resolved,closed', 'priority' => 'required|in:normal,high,urgent', 'assigned_to' => 'nullable|uuid|exists:users,id', 'forwarded_to' => 'sometimes|array|max:10', 'forwarded_to.*' => 'uuid|distinct|exists:users,id', 'internal_note' => 'nullable|string|max:5000']);
        DB::transaction(function () use ($r, $ticket, $v) {
            $t = DB::table('support_tickets')->where('id', $ticket)->lockForUpdate()->firstOrFail();
            abort_unless($t->version === $v['version'], 409);
            $transitions = ['submitted' => ['acknowledged', 'in_progress', 'escalated'], 'acknowledged' => ['in_progress', 'awaiting_parent', 'escalated'], 'in_progress' => ['awaiting_parent', 'escalated', 'resolved'], 'awaiting_parent' => ['in_progress', 'escalated', 'resolved'], 'escalated' => ['in_progress', 'awaiting_parent', 'resolved'], 'resolved' => ['closed', 'in_progress'], 'closed' => []];
            if ($v['status'] !== $t->status && ! in_array($v['status'], $transitions[$t->status], true)) {
                Domain::fail('status', 'Invalid ticket transition.');
            }
            if ($v['assigned_to'] ?? null) {
                $assignee = User::findOrFail($v['assigned_to']);
                abort_unless($assignee->hasPermission('communication.manage'), 422);
                $this->school->student($assignee, $t->student_id);
            }
            if (array_key_exists('forwarded_to', $v)) {
                DB::table('support_ticket_forwards')->where('support_ticket_id', $ticket)->delete();
            }
            foreach ($v['forwarded_to'] ?? [] as $u) {
                $user = User::findOrFail($u);
                abort_unless($user->is_active && ! $this->school->isParent($user), 422);
                $this->school->student($user, $t->student_id);
                DB::table('support_ticket_forwards')->updateOrInsert(['support_ticket_id' => $ticket, 'user_id' => $u], ['forwarded_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            if ($v['internal_note'] ?? null) {
                $this->message($r, $ticket, $v['internal_note'], true, [], (string) Str::uuid());
            }
            DB::table('support_tickets')->where('id', $ticket)->update(['status' => $v['status'], 'priority' => $v['priority'], 'assigned_to' => $v['assigned_to'] ?? null, 'version' => $t->version + 1, 'resolved_at' => $v['status'] === 'resolved' ? now() : $t->resolved_at, 'closed_at' => $v['status'] === 'closed' ? now() : $t->closed_at, 'updated_at' => now()]);
            Domain::insert('support_ticket_events', ['support_ticket_id' => $ticket, 'from_status' => $t->status, 'to_status' => $v['status'], 'created_by' => $r->user()->id]);
            Audit::record('support_ticket_'.$v['status'], $r->user()->id, 'support_tickets', $ticket, ['state' => $v['status']]);
            $this->notifications->user($t->guardian_user_id, 'ticket_status', 'ticket', $ticket, 'ticket-status:'.$ticket.':'.($t->version + 1), $t->student_id);
        });

        return Domain::response(['saved' => true]);
    }

    public function parents(Request $r)
    {
        $this->school->require($r->user(), 'announcements.manage');

        return Domain::response(['items' => DB::table('parent_guardians')->whereNotNull('user_id')->whereNotNull('mobile_verified_at')->whereIn('id', DB::table('parent_student_links')->where('is_active', true)->whereNotNull('verified_at')->whereIn('student_id', $this->school->students($r->user())->select('students.id'))->select('parent_guardian_id'))->orderBy('full_name')->get(['user_id', 'full_name'])]);
    }

    public function staff(Request $r)
    {
        $v = $r->validate(['student_id' => 'nullable|uuid|exists:students,id']);
        if (isset($v['student_id'])) {
            $this->school->student($r->user(), $v['student_id']);
        }
        $this->school->require($r->user(), 'communication.manage');
        $users = User::where('is_active', true)->whereIn('id', DB::table('user_role_assignments as a')->join('roles as r', 'r.id', '=', 'a.role_id')->whereIn('r.code', ['teacher', 'coordinator', 'school_administrator', 'super_admin', 'accountant_clerk'])->select('a.user_id'))->orderBy('name')->get();

        return Domain::response(['items' => $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'can_coordinate' => $u->hasPermission('communication.manage') && (! isset($v['student_id']) || $this->school->students($u)->where('students.id', $v['student_id'])->exists())])]);
    }
}
