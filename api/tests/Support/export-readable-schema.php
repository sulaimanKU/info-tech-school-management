<?php

// Only schema metadata from the guarded disposable database; never record values.
require __DIR__.'/assert-validation-database.php';

use Illuminate\Support\Facades\Schema;

$tables = [];
foreach (Schema::getTables() as $table) {
    $primary = collect(Schema::getIndexes($table['name']))->firstWhere('primary', true)['columns'] ?? [];
    $foreign = [];
    foreach (Schema::getForeignKeys($table['name']) as $key) {
        foreach ($key['columns'] as $index => $column) {
            $foreign[$column] = $key['foreign_table'].'.'.$key['foreign_columns'][$index];
        }
    }
    $tables[$table['name']] = array_map(fn ($c) => [
        'column' => $c['name'], 'type' => $c['type'], 'nullable' => $c['nullable'],
        'primary' => in_array($c['name'], $primary, true), 'references' => $foreign[$c['name']] ?? null,
    ], Schema::getColumns($table['name']));
}
file_put_contents(dirname(__DIR__, 3).'/docs/database-readable-schema.json', json_encode($tables, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo count($tables)." tables exported (metadata only).\n";
