<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('local') || DB::connection()->getDatabaseName() !== 'info_tech_school') {
    fwrite(STDERR, "Refusing a non-local development database.\n");
    exit(1);
}
$base = $argv[1] ?? 'http://127.0.0.1:8000/api/v1';
$url = parse_url($base);
$host = $url['host'] ?? '';
$private = $host === '127.0.0.1' || str_starts_with($host, '192.168.') || str_starts_with($host, '10.') || preg_match('/^172\.(1[6-9]|2[0-9]|3[01])\./', $host);
if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || ! $private || ($url['scheme'] ?? '') !== 'http' || ($url['port'] ?? null) !== 8000 || ($url['path'] ?? '') !== '/api/v1' || isset($url['user']) || isset($url['pass']) || isset($url['query'])) {
    throw new RuntimeException('Only a local private IPv4 development API on port 8000 is allowed.');
}
$validationExit = 0;

$password = bin2hex(random_bytes(24));
$user = null;
$step = 'create fictional temporary account';
$results = [];
function verify(bool $condition, string $label): void
{
    if (! $condition) {
        throw new RuntimeException($label);
    }
}
try {
    $user = User::create(['name' => 'Fictional Temporary Auth Validation', 'email' => 'temporary-auth-'.bin2hex(random_bytes(6)).'@example.test', 'password' => $password]);
    $user->roles()->attach(Role::where('code', 'super_admin')->firstOrFail());
    $step = 'successful HTTP staff login';
    $login = Http::asJson()->acceptJson()->timeout(20)->post($base.'/auth/login', ['email' => $user->email, 'password' => $password]);
    verify($login->status() === 200, $step);
    $data = $login->json('data');
    $token = $data['token'] ?? '';
    verify(strlen($token) === 64 && ($data['token_type'] ?? '') === 'Bearer', 'bearer contract');
    verify(($data['user']['id'] ?? null) === $user->id, 'user contract');
    verify(in_array('super_admin', array_column($data['user']['roles'] ?? [], 'code'), true), 'roles contract');
    verify(in_array('rbac.manage', $data['user']['permissions'] ?? [], true), 'permissions contract');
    verify(DB::table('authentication_sessions')->where('user_id', $user->id)->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->exists(), 'persisted hashed session');
    $results['staff_login'] = 200;
    $results['token_user_roles_permissions_session'] = true;
    $step = 'auth me';
    verify(Http::withToken($token)->acceptJson()->get($base.'/auth/me')->status() === 200, $step);
    $results['auth_me'] = 200;
    $step = 'logout';
    verify(Http::withToken($token)->asJson()->post($base.'/auth/logout', [])->status() === 200, $step);
    verify(Http::withToken($token)->acceptJson()->get($base.'/auth/me')->status() === 401, 'revoked session');
    $results['logout'] = 200;
    $results['revoked_session'] = 401;
    $step = 'invalid password';
    verify(Http::asJson()->post($base.'/auth/login', ['email' => $user->email, 'password' => $password.'invalid'])->status() === 401, $step);
    $results['invalid_password'] = 401;
    $step = 'missing email';
    $missing = Http::asJson()->post($base.'/auth/login', ['login' => $user->email, 'password' => $password]);
    verify($missing->status() === 422 && $missing->json('error.fields.email') !== null, $step);
    $results['missing_email'] = 422;
    $user->is_active = false;
    $user->save();
    $step = 'inactive staff';
    verify(Http::asJson()->post($base.'/auth/login', ['email' => $user->email, 'password' => $password])->status() === 401, $step);
    $results['inactive_staff'] = 401;
    $user->is_active = true;
    $user->save();
    $user->roles()->sync([Role::where('code', 'parent_guardian')->value('id')]);
    $step = 'parent-only password denial';
    verify(Http::asJson()->post($base.'/auth/login', ['email' => $user->email, 'password' => $password])->status() === 401, $step);
    $results['parent_only_staff_login'] = 401;
    echo json_encode(['base' => $base, 'results' => $results, 'secrets_printed' => false], JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Live acceptance failed at: '.$step.PHP_EOL);
    $validationExit = 1;
} finally {
    if ($user) {
        DB::table('authentication_sessions')->where('user_id', $user->id)->delete();
        $user->delete();
    }unset($password,$token);
}
exit($validationExit);
