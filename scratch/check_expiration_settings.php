<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use App\Services\Qlib;

$tenants = Tenant::all();

foreach ($tenants as $tenant) {
    echo "\n--- Tenant: {$tenant->id} ---\n";
    $tenant->run(function() {
        $ativa     = Qlib::qoption('pontos_expiracao_ativa');
        $dias      = Qlib::qoption('pontos_dias_expiracao');
        $notif     = Qlib::qoption('pontos_notificacao_ativa');
        $diasNotif = Qlib::qoption('pontos_notificacao_dias');

        echo "  pontos_expiracao_ativa:   " . ($ativa     ?? '[NÃO CONFIGURADO]') . "\n";
        echo "  pontos_dias_expiracao:    " . ($dias      ?? '[NÃO CONFIGURADO]') . "\n";
        echo "  pontos_notificacao_ativa: " . ($notif     ?? '[NÃO CONFIGURADO]') . "\n";
        echo "  pontos_notificacao_dias:  " . ($diasNotif ?? '[NÃO CONFIGURADO]') . "\n";
    });
}
