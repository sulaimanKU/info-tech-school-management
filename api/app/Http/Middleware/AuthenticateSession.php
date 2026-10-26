<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthenticateSession
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        abort_unless($token && strlen($token) === 64, 401);
        $session = DB::table('authentication_sessions')->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        abort_unless($session, 401);
        $user = User::find($session->user_id);
        abort_unless($user && $user->is_active, 401);
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('session_id', $session->id);

        return $next($request);
    }
}
