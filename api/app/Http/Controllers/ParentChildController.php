<?php

namespace App\Http\Controllers;

use App\Services\AnnouncementAccess;
use App\Services\ParentChildren;
use App\Support\Domain;
use Illuminate\Http\Request;

class ParentChildController extends Controller
{
    public function overview(Request $r, string $student, ParentChildren $children)
    {
        $child = $children->get($r->user(), $student)->firstOrFail();
        $diary = app(DiaryController::class)->visibleForStudent($r, $student);
        $recent = (clone $diary)->join('subjects as s', 's.id', '=', 'd.subject_id')->join('users as u', 'u.id', '=', 'd.teacher_user_id')->orderByDesc('d.date')->limit(3)->get(['d.id', 'd.date', 'd.topic', 'd.homework', 's.name as subject_name', 'u.name as teacher_name']);
        $subjects = (clone $diary)->join('subjects as s', 's.id', '=', 'd.subject_id')->select('s.id', 's.name')->distinct()->orderBy('s.name')->get();
        $announcements = app(AnnouncementAccess::class)->query($r->user(), true, $student)->orderByDesc('a.publish_at')->limit(3)->get(['a.id', 'a.title', 'a.kind', 'a.publish_at']);

        return Domain::response(['child' => $child, 'homework_count' => (clone $diary)->count(), 'recent_homework' => $recent, 'subjects' => $subjects, 'announcements' => $announcements])->header('Cache-Control','private, no-store');
    }
}
