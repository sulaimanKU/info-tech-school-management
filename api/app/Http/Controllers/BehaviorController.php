<?php

namespace App\Http\Controllers;

use App\Services\SchoolAccess;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BehaviorController extends Controller
{
    public function __construct(private SchoolAccess $access) {}

    public function index(Request $r, string $student)
    {
        $this->access->academic($r->user());
        $this->access->student($r->user(), $student);
        $q = DB::table('behavior_observations')->where('student_id', $student);
        if ($this->access->isParent($r->user())) {
            $q->where('status', 'published')->where('parent_visible', true);
        } elseif (! $r->user()->hasPermission('academics.review')) {
            $q->where('created_by', $r->user()->id);
        }

        return Domain::response(['items' => $q->orderByDesc('date')->get()]);
    }

    public function store(Request $r, string $student)
    {
        $this->access->require($r->user(), 'academics.write');
        $this->access->academic($r->user());
        $this->access->student($r->user(), $student);
        $v = $r->validate(['date' => 'required|date|before_or_equal:today', 'area' => 'required|in:punctuality,participation,homework_completion,respect,cooperation,cleanliness,responsibility,confidence,learning_habits,improvement', 'category' => 'required|in:exceptional,excellent,good,satisfactory,improving,needs_support', 'observation' => 'required|string|max:3000', 'parent_visible' => 'required|boolean']);
        $id = DB::transaction(function () use ($r, $student, $v) {
            $id = Domain::insert('behavior_observations', [...$v, 'student_id' => $student, 'created_by' => $r->user()->id, 'status' => 'private']);
            Audit::record('behavior_observed', $r->user()->id, 'behavior_observations', $id);

            return $id;
        });

        return Domain::response(['id' => $id], 201);
    }

    public function publish(Request $r, string $observation)
    {
        $this->access->require($r->user(), 'academics.review');
        $o = DB::table('behavior_observations')->where('id', $observation)->firstOrFail();
        $this->access->student($r->user(), $o->student_id);
        DB::transaction(function () use ($r, $observation) {
            DB::table('behavior_observations')->where('id', $observation)->update(['status' => 'published', 'published_by' => $r->user()->id, 'updated_at' => now()]);
            Audit::record('behavior_published', $r->user()->id, 'behavior_observations', $observation);
        });

        return Domain::response(['published' => true]);
    }
}
