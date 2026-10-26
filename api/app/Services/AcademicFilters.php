<?php

namespace App\Services;

use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Request filters only intersect SchoolAccess; no filter can grant access. */
final class AcademicFilters
{
    public function __construct(private SchoolAccess $access) {}

    public function values(Request $r, array $extra = []): array
    {
        $v = $r->validate($extra + ['academic_session_id' => 'nullable|uuid|exists:academic_sessions,id', 'class_id' => 'nullable|uuid|exists:classes,id', 'section_id' => 'nullable|uuid|exists:sections,id', 'subject_id' => 'nullable|uuid|exists:subjects,id', 'student_id' => 'nullable|uuid|exists:students,id', 'search' => 'nullable|string|max:100', 'status' => 'nullable|string|max:40', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'page' => 'nullable|integer|min:1']);
        if (! empty($v['student_id'])) {
            $this->access->student($r->user(), $v['student_id']);
        }
        if (! empty($v['section_id'])) {
            if (! $this->access->isParent($r->user())) {
                $this->access->section($r->user(), $v['section_id']);
            }
            $s = DB::table('sections')->find($v['section_id']);
            if ((isset($v['class_id']) && $s->class_id !== $v['class_id']) || (isset($v['academic_session_id']) && $s->academic_session_id !== $v['academic_session_id'])) {
                Domain::fail('section_id', 'Section does not belong to the selected class/session.');
            }
            if (! empty($v['subject_id']) && ! $this->access->isParent($r->user())) {
                $this->access->section($r->user(), $s->id, $v['subject_id']);
            }
        }
        if (! empty($v['subject_id']) && ! $this->access->isParent($r->user())) {
            abort_unless($this->access->subjects($r->user(), $v['section_id'] ?? null)->where('subjects.id', $v['subject_id'])->exists(), 403);
        }

        return $v;
    }

    public function sections(Request $r, array $v)
    {
        $q = $this->access->sections($r->user());
        foreach (['academic_session_id', 'class_id'] as $k) {
            if (! empty($v[$k])) {
                $q->where('sections.'.$k, $v[$k]);
            }
        }
        if (! empty($v['section_id'])) {
            $q->where('sections.id', $v['section_id']);
        }

        return $q;
    }

    public function records($q, Request $r, string $sectionColumn, string $dateColumn, array $v)
    {
        if (! $this->access->isParent($r->user())) {
            $q->whereIn($sectionColumn, $this->sections($r, $v)->select('sections.id'));
        }
        if (! empty($v['from'])) {
            $q->whereDate($dateColumn, '>=', $v['from']);
        }
        if (! empty($v['to'])) {
            $q->whereDate($dateColumn, '<=', $v['to']);
        }

        return $q;
    }

    public function students($q, Request $r, array $v)
    {
        if (! empty($v['academic_session_id']) || ! empty($v['class_id']) || ! empty($v['section_id']) || ! empty($v['enrollment_status'])) {
            $e = DB::table('enrollments as e')->join('sections as fs', 'fs.id', '=', 'e.section_id');
            foreach (['academic_session_id', 'class_id'] as $key) {
                if (! empty($v[$key])) {
                    $e->where('fs.'.$key, $v[$key]);
                }
            }
            if (! empty($v['section_id'])) {
                $e->where('e.section_id', $v['section_id']);
            }
            if (! empty($v['enrollment_status'])) {
                $e->where('e.status', $v['enrollment_status']);
            }
            $q->whereIn('students.id', $e->select('e.student_id'));
        }
        if (! empty($v['search'])) {
            $q->where(fn ($q) => $q->where('students.full_name', 'ilike', '%'.$v['search'].'%')->orWhere('students.registration_number', 'ilike', '%'.$v['search'].'%'));
        }
        if (! empty($v['status'])) {
            $q->where('students.status', $v['status']);
        }

        return $q;
    }
}
