<?php

namespace App\Services;

use App\Models\User;
use App\Support\Domain;

final class SessionIssuer
{
    public function issue(User $user, string $method): array
    {
        abort_unless($user->is_active, 401);
        $token = bin2hex(random_bytes(32));
        $expires = now()->addHours(12);
        Domain::insert('authentication_sessions', ['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'method' => $method, 'expires_at' => $expires]);

        return ['token' => $token, 'token_type' => 'Bearer', 'expires_at' => $expires->toIso8601String(), 'user' => $user->profile()];
    }
}
