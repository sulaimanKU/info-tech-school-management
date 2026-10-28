<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\SchoolAccess;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffDirectoryController extends Controller
{
    private function query()
    {
        return User::whereHas('roles', fn ($q) => $q->where('code', '!=', 'parent_guardian'));
    }

    private function safe(User $u): array
    {
        $p = DB::table('staff_profiles')->where('user_id', $u->id)->first();
        $assignments = [];
        if ($p) {
            foreach (['teacher_assignments', 'coordinator_assignments'] as $table) {
                foreach (DB::table($table.' as a')->join('sections as s', 's.id', '=', 'a.section_id')->join('classes as c', 'c.id', '=', 's.class_id')->join('academic_sessions as y', 'y.id', '=', 's.academic_session_id')->where('a.staff_profile_id', $p->id)->get(['a.*', 's.name as section_name', 's.class_id', 's.academic_session_id', 'c.name as class_name', 'y.name as session_name']) as $row) {
                    $assignments[] = [...(array) $row, 'kind' => $table === 'teacher_assignments' ? 'teacher' : 'coordinator', 'subject_name' => isset($row->subject_id) ? DB::table('subjects')->where('id', $row->subject_id)->value('name') : null];
                }
            }
        }

        return ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'is_active' => $u->is_active, 'roles' => $u->roles()->get(['code', 'name']), 'profile' => $p, 'assignments' => $assignments];
    }

    public function index(Request $r, SchoolAccess $access)
    {
        $access->require($r->user(), 'staff.manage');
        $v = $r->validate(['search' => 'nullable|string|max:100', 'role' => 'nullable|string|exists:roles,code', 'status' => 'nullable|in:active,inactive', 'page' => 'nullable|integer|min:1']);
        $q = $this->query();
        if (! empty($v['search'])) {
            $q->where(fn ($q) => $q->where('name', 'like', '%'.$v['search'].'%')->orWhere('email', 'like', '%'.$v['search'].'%'));
        }
        if (! empty($v['role'])) {
            $q->whereHas('roles', fn ($q) => $q->where('code', $v['role']));
        }
        if (! empty($v['status'])) {
            $q->where('is_active', $v['status'] === 'active');
        }
        $page = $q->orderBy('name')->paginate(30)->through(fn ($u) => $this->safe($u));

        return Domain::response([...$page->toArray(), 'items' => $page->items()]);
    }

    public function show(Request $r, SchoolAccess $a, string $user)
    {
        $a->require($r->user(), 'staff.manage');

        return Domain::response($this->safe($this->query()->findOrFail($user)));
    }

    private function lockSecurity(): void
    {
        Role::where('code', 'super_admin')->lockForUpdate()->firstOrFail();
    }

    private function target(Request $r, string $id): User
    {
        $target = $this->query()->whereKey($id)->lockForUpdate()->firstOrFail();
        if ($target->roles()->whereIn('code', ['super_admin', 'school_administrator'])->exists()) {
            abort_unless($r->user()->isSuperAdmin(), 403);
        }

        return $target;
    }

    private function lastAdmin(User $target, bool $active, ?string $role = null): void
    {
        if ($target->roles()->where('code', 'super_admin')->exists() && (! $active || ($role !== null && $role !== 'super_admin'))) {
            abort_unless($this->query()->where('is_active', true)->where('id', '!=', $target->id)->whereHas('roles', fn ($q) => $q->where('code', 'super_admin'))->exists(), 422, 'The last active Super Admin must remain usable.');
        }
    }

    private function revoke(User $u): void
    {
        DB::table('authentication_sessions')->where('user_id', $u->id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
        DB::table('push_devices')->where('user_id', $u->id)->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    public function update(Request $r, SchoolAccess $a, string $user)
    {
        $a->require($r->user(), 'staff.manage');
        $r->merge($r->has('email') ? ['email' => strtolower(trim((string) $r->input('email')))] : []);
        $v = $r->validate(['name' => 'sometimes|required|string|max:200', 'email' => 'sometimes|required|email|max:254|unique:users,email,'.$user, 'role' => 'sometimes|required|in:super_admin,school_administrator,coordinator,accountant_clerk,teacher']);
        DB::transaction(function () use ($r, $user, $v) {
            $this->lockSecurity();
            $u = $this->target($r, $user);
            if (isset($v['role']) && in_array($v['role'], ['super_admin', 'school_administrator'])) {
                abort_unless($r->user()->isSuperAdmin(), 403);
            }
            $this->lastAdmin($u, $u->is_active, $v['role'] ?? null);
            $u->fill(array_intersect_key($v, array_flip(['name', 'email'])))->save();
            if (isset($v['role'])) {
                $before = $u->roles()->pluck('code')->all();
                $u->roles()->sync([Role::where('code', $v['role'])->value('id')]);
                if ($before !== [$v['role']]) {
                    $this->revoke($u);
                    Audit::record('staff_role_changed', $r->user()->id, 'users', $u->id, ['changed_fields' => ['role']]);
                }
            }
            Audit::record('staff_account_updated', $r->user()->id, 'users', $u->id, ['changed_fields' => array_keys($v)]);
        });

        return Domain::response(['saved' => true]);
    }

    public function status(Request $r, SchoolAccess $access, string $user)
    {
        $access->require($r->user(), 'staff.manage');
        $v = $r->validate(['is_active' => 'required|boolean']);
        DB::transaction(function () use ($r, $user, $v) {
            $this->lockSecurity();
            $u = $this->target($r, $user);
            abort_if($u->id === $r->user()->id, 422);
            $this->lastAdmin($u, $v['is_active']);
            $u->is_active = $v['is_active'];
            $u->save();
            if (! $u->is_active) {
                $this->revoke($u);
            }
            Audit::record($u->is_active ? 'account_reactivated' : 'account_suspended', $r->user()->id, 'users', $u->id);
        });

        return Domain::response(['saved' => true]);
    }

    public function history(Request $r)
    {
        return Domain::response(DB::table('authentication_sessions')->where('user_id', $r->user()->id)->orderByDesc('created_at')->paginate(30, ['id', 'method', 'created_at', 'expires_at', 'revoked_at'])->through(fn ($s) => ['id' => $s->id, 'method' => $s->method, 'created_at' => $s->created_at, 'expires_at' => $s->expires_at, 'revoked_at' => $s->revoked_at, 'current' => $s->id === $r->attributes->get('session_id')]));
    }
}
