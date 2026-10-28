<?php

namespace App\Models;

use App\Services\GuardianIdentity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    use HasFactory, HasUuids;

    protected $attributes = ['is_active' => true];

    protected $fillable = ['name', 'email', 'password', 'is_active'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean'];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role_assignments')->withTimestamps();
    }

    public function hasPermission(string $code): bool
    {
        return $this->is_active && ($this->isSuperAdmin() || DB::table('user_role_assignments as ur')
            ->join('permission_role as pr', 'pr.role_id', '=', 'ur.role_id')
            ->join('permissions as p', 'p.id', '=', 'pr.permission_id')
            ->where('ur.user_id', $this->id)->where('p.code', $code)->exists());
    }

    public function isStaff(): bool
    {
        return $this->roles()->whereIn('code', ['super_admin', 'school_administrator', 'coordinator', 'accountant_clerk', 'teacher'])->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_active && $this->roles()->where('code', 'super_admin')->exists();
    }

    public function profile(): array
    {

        $guardian = Schema::hasTable('parent_guardians') ? DB::table('parent_guardians')->where('user_id', $this->id)->first() : null;
        $guardianProfile = $guardian ? ['photo_file_id' => $guardian->photo_file_id, 'id' => $guardian->id, 'full_name' => $guardian->full_name, 'cnic_masked' => (new GuardianIdentity)->mask($guardian->cnic), 'mobile_masked' => '********'.substr($guardian->mobile, -4)] : null;

        return ['guardian' => $guardianProfile, 'id' => $this->id, 'name' => $this->name, 'email' => $this->roles()->where('code', 'parent_guardian')->exists() && ! $this->roles()->where('code', '!=', 'parent_guardian')->exists() ? null : $this->email, 'permissions' => $this->isSuperAdmin() ? DB::table('permissions')->pluck('code') : DB::table('permissions as p')->join('permission_role as pr', 'pr.permission_id', '=', 'p.id')->join('user_role_assignments as ur', 'ur.role_id', '=', 'pr.role_id')->where('ur.user_id', $this->id)->distinct()->pluck('p.code'), 'roles' => $this->roles()->get(['roles.code', 'roles.name'])];
    }
}
