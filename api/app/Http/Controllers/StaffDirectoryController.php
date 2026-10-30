<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\SchoolAccess;
use App\Services\StaffProfileData;
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

        return ['id' => $u->id, 'account_code' => $u->account_code, 'name' => $u->name, 'email' => $u->email, 'mobile' => $u->mobile, 'address' => $u->address, 'photo_file_id' => $u->photo_file_id, 'is_active' => $u->is_active, 'roles' => $u->roles()->get(['code', 'name']), 'profile' => $p, 'assignments' => app(StaffProfileData::class)->assignments($p)];
    }

    public function index(Request $r, SchoolAccess $access)
    {
        $access->require($r->user(), 'staff.manage');
        $v = $r->validate(['search' => 'nullable|string|max:100', 'role' => 'nullable|string|exists:roles,code', 'status' => 'nullable|in:active,inactive', 'page' => 'nullable|integer|min:1']);
        $q = $this->query();
        if (! empty($v['search'])) {
            $q->where(fn ($q) => $q->where('name', 'like', '%'.$v['search'].'%')->orWhere('email', 'like', '%'.$v['search'].'%')->orWhere('account_code', 'like', '%'.$v['search'].'%'));
        }
        if (! empty($v['role'])) {
            $q->whereHas('roles', fn ($q) => $q->where('code', $v['role']));
        }
        if (! empty($v['status'])) {
            $q->where('is_active', $v['status'] === 'active');
        }
        $page = $q->orderBy('name')->paginate(30)->through(fn ($u) => $this->safe($u));

        return Domain::response([...$page->toArray(), 'items' => $page->items()])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $r, SchoolAccess $a, string $user)
    {
        $a->require($r->user(), 'staff.manage');

        return Domain::response($this->safe($this->query()->findOrFail($user)))->header('Cache-Control', 'private, no-store');
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
        $v = $r->validate(['password' => 'sometimes|required|string|min:4|max:1024|confirmed', 'password_confirmation' => 'required_with:password|string|max:1024', 'name' => 'sometimes|required|string|max:200', 'email' => 'sometimes|required|email|max:254|unique:users,email,'.$user, 'role' => 'sometimes|required|in:super_admin,school_administrator,coordinator,accountant_clerk,teacher', 'mobile' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\+?(?:[ ()-]*[0-9]){7,15}[ ()-]*$/'], 'address' => 'sometimes|nullable|string|max:1000', 'employment' => 'sometimes|required|array:employee_number,designation,joined_on,is_active']);
        DB::transaction(function () use ($r, $user, $v) {
            $this->lockSecurity();
            $u = $this->target($r, $user);
            if (isset($v['role']) && in_array($v['role'], ['super_admin', 'school_administrator'])) {
                abort_unless($r->user()->isSuperAdmin(), 403);
            }
            $this->lastAdmin($u, $u->is_active, $v['role'] ?? null);
            if (isset($v['employment'])) {
                $p = DB::table('staff_profiles')->where('user_id', $u->id)->lockForUpdate()->first();
                if (! $p) {
                    Domain::fail('employment', 'Link a staff employment profile before editing employment details.');
                }
                $employment = validator($v['employment'], ['employee_number' => 'sometimes|required|string|max:40|unique:staff_profiles,employee_number,'.$p->id, 'designation' => 'sometimes|required|string|max:100', 'joined_on' => 'sometimes|required|date', 'is_active' => 'sometimes|required|boolean'])->validate();
                if (isset($employment['joined_on']) && $employment['joined_on'] !== $p->joined_on && DB::table('payroll_items')->where('staff_profile_id', $p->id)->exists()) {
                    Domain::fail('employment.joined_on', 'Joining date is locked after payroll generation.');
                }
                DB::table('staff_profiles')->where('id', $p->id)->update([...$employment, 'updated_at' => now()]);
            }
            $u->fill(array_intersect_key($v, array_flip(['name', 'email', 'password'])))->save();
            if (array_key_exists('mobile', $v) || array_key_exists('address', $v)) {
                DB::table('users')->where('id', $u->id)->update(array_intersect_key($v, array_flip(['mobile', 'address'])));
            }
            if (isset($v['password'])) {
                $this->revoke($u);
                Audit::record('staff_password_reset', $r->user()->id, 'users', $u->id);
            }
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
