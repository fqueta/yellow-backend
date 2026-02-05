<?php

use App\Models\Tenant;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

$tenantId = 'api-mileto';
$tenantDomain = 'api-mileto.localhost'; // Ensure this matches what you use in browser

echo "Creating tenant $tenantId...\n";

if (Tenant::find($tenantId)) {
    echo "Tenant already exists.\n";
    $tenant = Tenant::find($tenantId);
} else {
    $tenant = Tenant::create([
        'id' => $tenantId,
        'ativo' => 's',
        'excluido' => 'n',
        'deletado' => 'n',
    ]);
    echo "Tenant created.\n";
}

echo "Creating domain $tenantDomain...\n";

if ($tenant->domains()->where('domain', $tenantDomain)->exists()) {
    echo "Domain already exists.\n";
} else {
    $tenant->domains()->create(['domain' => $tenantDomain]);
    echo "Domain created.\n";
}

echo "Done.\n";
