<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AnnouncementAccess
{
    public function __construct(private SchoolAccess $school) {}

    public function mayManage(User $u, object $a): bool
    {
        if (! $u->hasPermission('announcements.manage')) {
            return false;
        }
        if ($u->hasPermission('students.read.all')) {
            return true;
        }
        if (in_array($a->audience, ['class', 'section']) && $a->section_id) {
            return $this->school->sections($u)->where('sections.id', $a->section_id)->exists();
        }
        if ($a->audience === 'parent') {
            return DB::table('parent_student_links as l')->join('parent_guardians as g', 'g.id', '=', 'l.parent_guardian_id')->where('g.user_id', $a->target_user_id)->where('l.is_active', true)->whereNotNull('l.verified_at')->whereIn('l.student_id', $this->school->students($u)->select('students.id'))->exists();
        }

        return false;
    }

    public function query(User $u, bool $includeDrafts = true, ?string $student = null)
    {
        $q = DB::table('announcements as a');
        if ($includeDrafts && $u->hasPermission('announcements.manage') && $u->hasPermission('students.read.all')) {
            return $q;
        }
        $parent = $this->school->isParent($u);
        $sections = $parent ? DB::table('enrollments')->whereIn('student_id', $this->school->students($u)->when($student, fn ($q) => $q->where('students.id', $student))->select('students.id'))->where('starts_on', '<=', now()->toDateString())->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now()->toDateString()))->select('section_id') : $this->school->sections($u)->select('sections.id');
        $public = function ($q) use ($u, $parent, $sections) {
            $q->where('a.status', 'published')->where('a.publish_at', '<=', now())->where(fn ($q) => $q->whereNull('a.expires_at')->orWhere('a.expires_at', '>', now()))->where(function ($q) use ($u, $parent, $sections) {
                $q->where('a.audience', 'all_users');
                if ($parent) {
                    $q->orWhere('a.audience', 'all_parents')->orWhere(fn ($q) => $q->where('a.audience', 'parent')->where('a.target_user_id', $u->id));
                } else {
                    $q->orWhere('a.audience', 'staff');
                }
                if ($u->roles()->where('code', 'teacher')->exists()) {
                    $q->orWhere('a.audience', 'teachers');
                }
                $q->orWhere(fn ($q) => $q->where('a.audience', 'role')->whereIn('a.target_role_code', $u->roles()->select('code')));
                $q->orWhere(fn ($q) => $q->whereIn('a.audience', ['class', 'section'])->whereIn('a.section_id', clone $sections));
                $q->orWhere(fn ($q) => $q->where('a.audience', 'class')->whereIn('a.class_id', DB::table('sections')->whereIn('id', clone $sections)->select('class_id')));
            });
        };

        return $q->where(function ($q) use ($u, $includeDrafts, $public, $sections) {
            $q->where($public);
            if ($includeDrafts && $u->hasPermission('announcements.manage')) {
                $q->orWhere(fn ($q) => $q->whereIn('a.audience', ['class', 'section'])->whereIn('a.section_id', clone $sections));
                $q->orWhere(fn ($q) => $q->where('a.audience', 'parent')->whereIn('a.target_user_id', DB::table('parent_guardians as g')->join('parent_student_links as l', 'l.parent_guardian_id', '=', 'g.id')->where('l.is_active', true)->whereNotNull('l.verified_at')->whereIn('l.student_id', $this->school->students($u)->select('students.id'))->select('g.user_id')));
            }
        });
    }
}
