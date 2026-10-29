<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $input = $r->validate(['email' => ['required', 'email', 'max:254'], 'password' => ['required', 'string', 'max:1024']]);
        $user = User::where('email', strtolower($input['email']))->first();
        // A fixed dummy hash makes unknown-user requests perform the same expensive hash check.
        $valid = Hash::check($input['password'], $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (! $valid || ! $user?->is_active || ! $user->roles()->where('code', '!=', 'parent_guardian')->exists()) {
            Audit::record('login_failed', $user?->id);
            abort(401);
        }

        return DB::transaction(function () use ($user) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            // A reset may commit after the initial password check but before this lock.
            abort_unless(hash_equals($user->password, $locked->password) && $locked->is_active && $locked->roles()->where('code', '!=', 'parent_guardian')->exists(), 401);
            $token = bin2hex(random_bytes(32));
            $expires = now()->addHours(12);
            DB::table('authentication_sessions')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'method' => 'staff_password', 'expires_at' => $expires, 'created_at' => now(), 'updated_at' => now()]);
            Audit::record('login_succeeded', $user->id);

            return response()->json(['data' => ['token' => $token, 'token_type' => 'Bearer', 'expires_at' => $expires->toIso8601String(), 'user' => $locked->profile()], 'error' => null]);
        });
    }

    public function me(Request $r)
    {
        return response()->json(['data' => $r->user()->profile(), 'error' => null]);
    }

    public function logout(Request $r)
    {
        DB::transaction(function () use ($r) {
            DB::table('authentication_sessions')->where('id', $r->attributes->get('session_id'))->update(['revoked_at' => now(), 'updated_at' => now()]);
            if (Schema::hasTable('push_devices')) {
                DB::table('push_devices')->where('authentication_session_id', $r->attributes->get('session_id'))->update(['revoked_at' => now(), 'updated_at' => now()]);
            }
            Audit::record('logout', $r->user()->id);
        });

        return response()->json(['data' => ['logged_out' => true], 'error' => null]);
    }
}
