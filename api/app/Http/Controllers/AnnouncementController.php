<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AnnouncementAccess;
use App\Services\NotificationService;
use App\Services\SchoolAccess;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends Controller
{
    public function __construct(private SchoolAccess $school, private AnnouncementAccess $access) {}

    public function index(Request $r)
    {
        $v = $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'student_id' => 'nullable|uuid', 'status' => 'nullable|in:draft,published,archived', 'search' => 'nullable|string|max:100', 'read' => 'nullable|in:read,unread']);
        if (isset($v['student_id'])) {
            $this->school->student($r->user(), $v['student_id']);
        }
        $q = $this->access->query($r->user(), true, $v['student_id'] ?? null);
        if (isset($v['from'])) {
            $q->whereDate('a.publish_at', '>=', $v['from']);
        }if (isset($v['to'])) {
            $q->whereDate('a.publish_at', '<=', $v['to']);
        }
        if (isset($v['status'])) {
            $q->where('a.status', $v['status']);
        }if (isset($v['search'])) {
            $q->where('a.title', 'like', '%'.$v['search'].'%');
        }
        if (isset($v['read'])) {
            $q->whereExists(function ($q) use ($r) {
                $q->selectRaw('1')->from('announcement_reads as rr')->whereColumn('rr.announcement_id', 'a.id')->where('rr.user_id', $r->user()->id);
            }, 'and', $v['read'] === 'unread');
        }

        return Domain::response($q->leftJoin('announcement_reads as ar', fn ($j) => $j->on('ar.announcement_id', '=', 'a.id')->where('ar.user_id', $r->user()->id))->orderByDesc('a.publish_at')->orderByDesc('a.created_at')->paginate(30, ['a.*', 'ar.read_at'])->through(fn ($a) => [...(array) $a, 'can_manage' => $this->access->mayManage($r->user(), $a)]));
    }

    public function show(Request $r, string $announcement)
    {
        $a = $this->access->query($r->user())->where('a.id', $announcement)->firstOrFail();
        DB::table('announcement_reads')->updateOrInsert(['announcement_id' => $announcement, 'user_id' => $r->user()->id], ['read_at' => now()]);

        return Domain::response([...(array) $a, 'can_manage' => $this->access->mayManage($r->user(), $a)]);
    }

    public function store(Request $r, ?string $announcement = null)
    {
        $this->school->require($r->user(), 'announcements.manage');
        $v = $r->validate(['title' => 'required|string|max:200', 'body' => 'required|string|max:10000', 'kind' => 'required|in:notice,holiday,exam,result,ptm,fee_reminder,event,emergency,homework,admission', 'audience' => 'required|in:all_users,all_parents,class,section,role,parent,teachers,staff', 'section_id' => 'nullable|required_if:audience,section|uuid|exists:sections,id', 'class_id' => 'nullable|uuid|exists:classes,id', 'target_role_code' => 'nullable|required_if:audience,role|string|exists:roles,code', 'target_user_id' => 'nullable|required_if:audience,parent|uuid|exists:users,id', 'status' => 'required|in:draft,published,archived', 'publish_at' => 'nullable|date', 'expires_at' => 'nullable|date|after:publish_at', 'version' => 'required|integer|min:0']);
        if ($v['audience'] === 'parent') {
            abort_unless(User::findOrFail($v['target_user_id'])->roles()->where('code', 'parent_guardian')->exists(), 422);
        }
        if (in_array($v['audience'], ['class', 'section']) && empty($v['section_id']) && empty($v['class_id'])) {
            Domain::fail('section_id', 'Select an authorized class or section.');
        }
        $audience = (object) [...$v, 'section_id' => $v['section_id'] ?? null, 'class_id' => $v['class_id'] ?? null, 'target_user_id' => $v['target_user_id'] ?? null];
        abort_unless($this->access->mayManage($r->user(), $audience), 403);
        if ($announcement) {
            abort_unless($this->access->mayManage($r->user(), DB::table('announcements')->where('id', $announcement)->firstOrFail()), 403);
        }
        $id = DB::transaction(function () use ($r, $v, $announcement) {
            $old = $announcement ? DB::table('announcements')->where('id', $announcement)->lockForUpdate()->firstOrFail() : null;
            abort_unless(($old?->version ?? 0) === $v['version'], 409);
            $data = array_intersect_key($v, array_flip(['title', 'body', 'kind', 'audience', 'status', 'publish_at', 'expires_at']));
            $data['section_id'] = in_array($v['audience'], ['class', 'section']) ? ($v['section_id'] ?? null) : null;
            $data['class_id'] = $v['audience'] === 'class' && empty($v['section_id']) ? ($v['class_id'] ?? null) : null;
            $data['target_role_code'] = $v['audience'] === 'role' ? $v['target_role_code'] : null;
            $data['target_user_id'] = $v['audience'] === 'parent' ? $v['target_user_id'] : null;
            $data['version'] = ($old?->version ?? 0) + 1;
            if ($v['status'] === 'published') {
                $data['publish_at'] = $v['publish_at'] ?? now();
                $data['published_by'] = $r->user()->id;
            }
            if ($old) {
                $id = $old->id;
                DB::table('announcements')->where('id', $id)->update([...$data, 'updated_at' => now()]);
            } else {
                $id = Domain::insert('announcements', [...$data, 'created_by' => $r->user()->id]);
            }Audit::record('announcement_'.$v['status'], $r->user()->id, 'announcements', $id, ['version' => $data['version']]);

            return $id;
        });
        $this->notify($id);

        return Domain::response(['id' => $id], $announcement ? 200 : 201);
    }

    public function notify(string $id): void
    {
        $a = DB::table('announcements')->find($id);
        if ($a->status !== 'published' || now()->lt($a->publish_at) || ($a->expires_at && now()->gte($a->expires_at))) {
            return;
        }
        User::where('is_active', true)->orderBy('id')->chunk(100, function ($users) use ($a) {
            foreach ($users as $u) {
                if ($this->access->query($u, false)->where('a.id', $a->id)->exists()) {
                    app(NotificationService::class)->user($u->id, 'announcement', 'announcement', $a->id, 'announcement:'.$a->id.':'.$a->version);
                }
            }
        });
    }
}
