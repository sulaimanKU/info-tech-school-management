<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local')) {
    fwrite(STDERR, "Local diagnosis only.\n");
    exit(1);
}
$queries = [];
DB::connection()->beforeExecuting(function ($sql) use (&$queries) {
    if (preg_match('/^\s*(select|insert|update|delete).*?\b(?:from|into|update)\s+["`]?([a-z_]+)/is', $sql, $m)) {
        $queries[] = ['operation' => strtolower($m[1]), 'table' => $m[2]];
    }
});
$request = Request::create('/api/v1/auth/login', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => '127.0.0.2'], json_encode(['email' => 'fictional-diagnosis-'.bin2hex(random_bytes(6)).'@example.test', 'password' => bin2hex(random_bytes(20))]));
$response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);
echo json_encode(['http_status' => $response->getStatusCode(), 'database_driver_loaded' => in_array(DB::getDriverName() === 'mariadb' ? 'mysql' : DB::getDriverName(), PDO::getAvailableDrivers(), true), 'first_database_operation' => $queries[0] ?? null, 'cache_store' => config('cache.default')], JSON_PRETTY_PRINT).PHP_EOL;
