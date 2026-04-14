<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$columns = Illuminate\Support\Facades\DB::select('DESCRIBE points');
foreach ($columns as $column) {
    if ($column->Field === 'status') {
        print_r($column);
    }
}
