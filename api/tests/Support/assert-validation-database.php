<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Run before any isolated validation migration. Refuse cached/live configuration.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$driver = DB::getDriverName();
$config = DB::connection()->getConfig();
$valid = ($config['host'] ?? null) === '127.0.0.1' && (
    ($driver === 'pgsql' && (string) ($config['port'] ?? '') === '55433' && ($config['database'] ?? null) === 'infotech_school_test') ||
    (in_array($driver, ['mysql', 'mariadb'], true) && (string) ($config['port'] ?? '') === '53317' && ($config['database'] ?? null) === 'infotech_compatibility_test')
);
if (! $valid || $app->configurationIsCached()) {
    fwrite(STDERR, "Refusing validation: use an isolated local test database without cached configuration.\n");
    exit(1);
}
$name = DB::selectOne($driver === 'pgsql' ? 'select current_database() as name' : 'select database() as name')->name;
if ($name !== $config['database']) {
    exit(1);
}
echo "Isolated validation database verified.\n";
