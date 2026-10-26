<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Run before any isolated validation migration. Refuse cached/live configuration.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = config('database.connections.pgsql');
if (config('database.default') !== 'pgsql' || ($config['host'] ?? null) !== '127.0.0.1' || (string) ($config['port'] ?? '') !== '55433' || ($config['database'] ?? null) !== 'infotech_school_test') {
    fwrite(STDERR, "Refusing validation: connection must be the isolated local test database on port 55433.\n");
    exit(1);
}
if (DB::selectOne('select current_database() as name')->name !== 'infotech_school_test') {
    exit(1);
}
echo "Isolated validation database verified.\n";
