<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
echo 'PostgreSQL PDO driver loaded: '.(in_array('pgsql', PDO::getAvailableDrivers(), true) ? 'yes' : 'no').PHP_EOL;
try {
    DB::select('select 1');
    echo 'Database query succeeded.'.PHP_EOL;
} catch (Throwable $e) {
    $cause = $e;
    while ($cause->getPrevious()) {
        $cause = $cause->getPrevious();
    }echo 'Root exception type: '.get_class($cause).PHP_EOL;
    echo str_contains($cause->getMessage(), 'could not find driver') ? 'Database driver unavailable.'.PHP_EOL : 'Database connectivity failure; details withheld.'.PHP_EOL;
    exit(1);
}
