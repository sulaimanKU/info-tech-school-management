<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\SchoolAccess;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RbacController extends Controller
{
    public function index(Request $r, SchoolAccess $a)
    {
        $a->require($r->user(), 'rbac.manage');

        return Domain::response(['roles' => Role::orderBy('code')->get()->map(fn ($role) => ['code' => $role->code, 'name' => $role->name, 'permissions' => DB::table('permission_role as pr')->join('permissions as p', 'p.id', '=', 'pr.permission_id')->where('pr.role_id', $role->id)->pluck('p.code')]), 'permissions' => DB::table('permissions')->orderBy('code')->get(['code', 'name'])]);
    }

    public function update(Request $r, SchoolAccess $a, string $role)
    {
        $a->require($r->user(), 'rbac.manage');
        abort_if(in_array($role, ['super_admin', 'parent_guardian']), 403);
        $v = $r->validate(['permissions' => 'present|array|max:100', 'permissions.*' => 'string|distinct|exists:permissions,code']);
        // Non-super roles may never delegate security administration to themselves.
        abort_if(in_array('rbac.manage', $v['permissions'], true), 403);
        DB::transaction(function () use ($r, $role, $v) {
            $target = Role::where('code', $role)->lockForUpdate()->firstOrFail();
            $old = DB::table('permission_role')->where('role_id', $target->id)->pluck('permission_id');
            DB::table('permission_role')->where('role_id', $target->id)->delete();
            foreach (DB::table('permissions')->whereIn('code', $v['permissions'])->pluck('id') as $p) {
                DB::table('permission_role')->insert(['role_id' => $target->id, 'permission_id' => $p]);
            }
            Audit::record('role_permissions_updated', $r->user()->id, 'roles', $target->id, ['changed_fields' => ['permissions'], 'count' => count($v['permissions'])]);
        });

        return Domain::response(['saved' => true]);
    }
}
