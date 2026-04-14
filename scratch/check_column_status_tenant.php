<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

$tenant = Tenant::first();
if ($tenant) {
    echo "--- Estrutura no Tenant: {$tenant->id} ---\n";
    $tenant->run(function() {
        $columns = DB::select('DESCRIBE points');
        foreach ($columns as $column) {
            if ($column->Field === 'status') {
                print_r($column);
            }
        }
    });
} else {
    echo "Nenhum tenant encontrado.\n";
}
