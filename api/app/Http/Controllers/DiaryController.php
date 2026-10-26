<?php

namespace App\Http\Controllers;

use App\Services\AcademicCalendar;
use App\Services\AcademicFilters;
use App\Services\NotificationService;
use App\Services\SchoolAccess;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiaryController extends Controller
{
    public function __construct(private SchoolAccess $access, private AcademicCalendar $calendar) {}

    public function visible(Request $r)
    {
        $this->access->academic($r->user());
        $q = DB::table('diary_entries as d');
        if ($this->access->isParent($r->user())) {
            $children = $this->access->students($r->user())->pluck('students.id');
            $q->where('d.status', 'published')->whereExists(function ($e) use ($children) {
                $e->selectRaw('1')->from('enrollments as e')->whereIn('e.student_id', $children)->whereColumn('e.section_id', 'd.section_id')->whereColumn('e.starts_on', '<=', 'd.date')->where(fn ($x) => $x->whereNull('e.ends_on')->orWhereColumn('e.ends_on', '>=', 'd.date'))->where(function ($x) use ($children) {
                    $x->where('d.whole_class', true)->orWhereExists(fn ($t) => $t->selectRaw('1')->from('diary_students as t')->whereColumn('t.diary_entry_id', 'd.id')->whereIn('t.student_id', $children));
                });
            });
        } else {
            $q->whereIn('d.section_id', $this->access->sections($r->user())->select('sections.id'));
            if (! $r->user()->hasPermission('academics.review')) {
                $q->whereIn('d.subject_id', $this->access->subjects($r->user())->select('subjects.id'));
                $q->whereExists(fn ($a) => $a->selectRaw('1')->from('teacher_assignments as ta')->join('staff_profiles as tp', 'tp.id', '=', 'ta.staff_profile_id')->where('tp.user_id', $r->user()->id)->where('tp.is_active', true)->where('ta.is_active', true)->where(fn ($q) => $q->whereNull('ta.starts_on')->orWhere('ta.starts_on', '<=', now()->toDateString()))->where(fn ($q) => $q->whereNull('ta.ends_on')->orWhere('ta.ends_on', '>=', now()->toDateString()))->whereColumn('ta.section_id', 'd.section_id')->whereColumn('ta.subject_id', 'd.subject_id'));
                $q->where(fn ($x) => $x->where('d.teacher_user_id', $r->user()->id)->orWhere('d.status', 'published'));
            }
        }

        $this->access->academicRecords($q, $r->user(), 'd.section_id', 'd.subject_id', 'd.date');

        return $q;
    }

    public function visibleForStudent(Request $r, string $student, $q = null)
    {
        $q = $q ?? $this->visible($r);
        $s = $student;
        $this->access->student($r->user(), $s);
        $q->whereExists(fn ($e) => $e->selectRaw('1')->from('enrollments as e')->where('e.student_id', $s)->whereColumn('e.section_id', 'd.section_id')->whereColumn('e.starts_on', '<=', 'd.date')->where(fn ($x) => $x->whereNull('e.ends_on')->orWhereColumn('e.ends_on', '>=', 'd.date')))->where(fn ($x) => $x->where('d.whole_class', true)->orWhereExists(fn ($e) => $e->selectRaw('1')->from('diary_students as t')->where('t.student_id', $s)->whereColumn('t.diary_entry_id', 'd.id')));

        return $q;
    }

    public function index(Request $r)
    {
        $filters = app(AcademicFilters::class);
        $v = $filters->values($r, ['date' => 'nullable|date', 'status' => 'nullable|in:draft,published']);
        $q = $this->visible($r);
        $filters->records($q, $r, 'd.section_id', 'd.date', $v);
        if (! empty($v['status'])) {
            $q->where('d.status', $v['status']);
        }
        if (! empty($v['search'])) {
            $q->where(fn ($q) => $q->where('d.topic', 'ilike', '%'.$v['search'].'%')->orWhere('d.homework', 'ilike', '%'.$v['search'].'%'));
        }
        foreach (['section_id', 'subject_id', 'date'] as $key) {
            if (isset($v[$key])) {
                $q->where('d.'.$key, $v[$key]);
            }
        }
        if (isset($v['student_id'])) {
            $q = $this->visibleForStudent($r, $v['student_id'], $q);
        }

        return Domain::response($q->join('users as u', 'u.id', '=', 'd.teacher_user_id')->join('subjects as s', 's.id', '=', 'd.subject_id')->orderByDesc('d.date')->paginate(30, ['d.*', 'u.name as teacher_name', 's.name as subject_name'])->through(function ($d) use ($r) {
            if ($this->access->isParent($r->user())) {
                unset($d->client_id, $d->payload_hash, $d->reviewed_by);
            }

            return $d;
        }));
    }

    public function show(Request $r, string $diary)
    {
        $v = $r->validate(['student_id' => 'nullable|uuid']);
        $q = isset($v['student_id']) ? $this->visibleForStudent($r, $v['student_id']) : $this->visible($r);
        $d = $q->where('d.id', $diary)->firstOrFail();
        $data = (array) $d;
        $data['attachments'] = DB::table('diary_attachments as a')->join('private_files as f', 'f.id', '=', 'a.private_file_id')->where('a.diary_entry_id', $diary)->get(['f.id', 'f.mime', 'f.size']);
        $data['students'] = DB::table('diary_students')->where('diary_entry_id', $diary)->pluck('student_id');

        if ($this->access->isParent($r->user())) {
            $data['students'] = collect($data['students'])->intersect($this->access->students($r->user())->pluck('students.id'))->values();
            unset($data['client_id'],$data['payload_hash'],$data['reviewed_by']);
        }

        return Domain::response($data);
    }

    public function save(Request $r, ?string $diary = null)
    {
        $this->access->require($r->user(), 'academics.write');
        $v = $r->validate(['section_id' => 'required|uuid|exists:sections,id', 'subject_id' => 'required|uuid|exists:subjects,id', 'date' => 'required|date', 'topic' => 'required|string|max:300', 'homework' => 'required|string|max:10000', 'whole_class' => 'required|boolean', 'status' => 'required|in:draft,published', 'version' => 'required|integer|min:0', 'client_id' => 'required|uuid', 'students' => 'present|array|max:200', 'students.*' => 'uuid|distinct|exists:students,id', 'attachments' => 'present|array|max:10', 'attachments.*' => 'uuid|distinct|exists:private_files,id']);
        $this->access->section($r->user(), $v['section_id'], $v['subject_id'], $v['date']);
        $id = DB::transaction(function () use ($r, $v, $diary) {
            $old = $diary ? DB::table('diary_entries')->where('id', $diary)->lockForUpdate()->firstOrFail() : DB::table('diary_entries')->where('client_id', $v['client_id'])->lockForUpdate()->first();
            $payloadHash = hash('sha256', json_encode(array_intersect_key($v, array_flip(['section_id', 'subject_id', 'date', 'topic', 'homework', 'whole_class', 'status', 'students', 'attachments']))));
            if ($old && ! $diary) {
                abort_unless(hash_equals($old->payload_hash, $payloadHash), 409);
                abort_unless($old->teacher_user_id === $r->user()->id, 403);

                return $old->id;
            }
            if ($old) {
                abort_unless($old->teacher_user_id === $r->user()->id || $r->user()->hasPermission('academics.review'), 403);
                abort_unless($old->version === $v['version'], 409);
                if ($old->section_id !== $v['section_id'] || $old->subject_id !== $v['subject_id']) {
                    Domain::fail('section_id', 'Revise content without moving the diary to another assignment.');
                }
            } elseif ($v['version'] !== 0) {
                abort(409);
            }
            $roster = $this->calendar->roster($v['section_id'], $v['date'])->pluck('id')->all();
            if (! $v['whole_class'] && (! $v['students'] || array_diff($v['students'], $roster))) {
                Domain::fail('students', 'Select students from the class roster.');
            }
            foreach ($v['attachments'] as $f) {
                $file = DB::table('private_files')->where('id', $f)->first();
                if ($file->section_id !== $v['section_id'] || $file->purpose !== 'diary' || ($file->uploaded_by !== $r->user()->id && ! $r->user()->hasPermission('academics.review'))) {
                    abort(403);
                }
            }
            $data = array_intersect_key($v, array_flip(['section_id', 'subject_id', 'date', 'topic', 'homework', 'whole_class', 'status']));
            $data['payload_hash'] = $payloadHash;
            $data['version'] = ($old?->version ?? 0) + 1;
            $data['reviewed_at'] = null;
            $data['reviewed_by'] = null;
            if ($old) {
                $id = $old->id;
                DB::table('diary_entries')->where('id', $id)->update([...$data, 'updated_at' => now()]);
            } else {
                $id = Domain::insert('diary_entries', [...$data, 'teacher_user_id' => $r->user()->id, 'client_id' => $v['client_id']]);
            }
            DB::table('diary_students')->where('diary_entry_id', $id)->delete();
            if (! $v['whole_class']) {
                foreach ($v['students'] as $s) {
                    DB::table('diary_students')->insert(['diary_entry_id' => $id, 'student_id' => $s]);
                }
            }
            DB::table('diary_attachments')->where('diary_entry_id', $id)->delete();
            foreach ($v['attachments'] as $f) {
                DB::table('diary_attachments')->insert(['diary_entry_id' => $id, 'private_file_id' => $f]);
            }
            Domain::insert('diary_revisions', ['diary_entry_id' => $id, 'version' => $data['version'], 'snapshot' => json_encode([...$data, 'students' => $v['students'], 'attachments' => $v['attachments']]), 'created_by' => $r->user()->id]);
            Audit::record('diary_'.$v['status'], $r->user()->id, 'diary_entries', $id, ['version' => $data['version']]);
            if ($v['status'] === 'published') {
                foreach ($v['whole_class'] ? $roster : $v['students'] as $student) {
                    app(NotificationService::class)->parents($student, 'homework', 'diary', $id, 'diary:'.$id.':'.$data['version'].':'.$student);
                }
            }

            return $id;
        });

        return Domain::response(['id' => $id]);
    }

    public function review(Request $r, string $diary)
    {
        $this->access->require($r->user(), 'academics.review');
        $v = $r->validate(['student_id' => 'nullable|uuid']);
        $q = isset($v['student_id']) ? $this->visibleForStudent($r, $v['student_id']) : $this->visible($r);
        $d = $q->where('d.id', $diary)->firstOrFail();
        DB::transaction(function () use ($r, $diary) {
            DB::table('diary_entries')->where('id', $diary)->update(['reviewed_at' => now(), 'reviewed_by' => $r->user()->id, 'updated_at' => now()]);
            Audit::record('diary_reviewed', $r->user()->id, 'diary_entries', $diary);
        });

        return Domain::response(['reviewed' => true]);
    }

    public function revisions(Request $r, string $diary)
    {
        $v = $r->validate(['student_id' => 'nullable|uuid']);
        $q = isset($v['student_id']) ? $this->visibleForStudent($r, $v['student_id']) : $this->visible($r);
        $d = $q->where('d.id', $diary)->firstOrFail();
        abort_if($this->access->isParent($r->user()), 403);

        return Domain::response(['items' => DB::table('diary_revisions')->where('diary_entry_id', $diary)->orderBy('version')->get()]);
    }
}
