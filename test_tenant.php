<?php

use App\Models\Tenant;
use Illuminate\Support\Str;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$logFile = __DIR__ . '/test_output.log';
file_put_contents($logFile, "Starting test...\n");

function logMsg($msg) {
    global $logFile;
    file_put_contents($logFile, $msg . "\n", FILE_APPEND);
    echo $msg . "\n";
}

try {
    logMsg("Custom columns: " . implode(', ', Tenant::getCustomColumns()));

    $id = 'test-' . Str::random(5);
    logMsg("Creating tenant with ID: $id");

    $tenant = Tenant::create([
        'id' => $id,
        'ativo' => 's',
        'excluido' => 'n',
        'deletado' => 'n'
    ]);
    
    logMsg("Tenant created successfully: " . $tenant->id);
    
    logMsg("Creating domain...");
    $tenant->domains()->create(['domain' => $id . '.localhost']);
    logMsg("Domain created successfully.");

    logMsg("Initializing tenancy...");
    tenancy()->initialize($tenant);
    logMsg("Tenant initialized. Database: " . config('database.connections.tenant.database'));

} catch (\Exception $e) {
    logMsg("Error: " . $e->getMessage());
    logMsg($e->getTraceAsString());
}
