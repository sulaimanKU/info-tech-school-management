<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$counts = [];
foreach (['migrations', 'roles', 'permissions', 'classes', 'fee_schedules', 'teacher_assignments', 'coordinator_assignments'] as $table) {
    $counts[$table] = Schema::hasTable($table) ? DB::table($table)->count() : null;
}
echo json_encode(['database' => DB::connection()->getDatabaseName(), 'counts' => $counts], JSON_PRETTY_PRINT).PHP_EOL;
