<?php
/**
 * Script para configurar as opções de expiração de pontos no tenant.
 * Edite as variáveis abaixo antes de rodar.
 * 
 * ATENÇÃO: Rode apenas no ambiente de DEV para testes!
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use App\Services\Qlib;
use Illuminate\Support\Facades\DB;

// ======== CONFIGURAÇÕES — EDITE AQUI ========
$DIAS_EXPIRACAO      = 365;   // Quantos dias os pontos valem a partir de hoje
$EXPIRACAO_ATIVA     = 's';   // 's' = ativada
$NOTIFICACAO_ATIVA   = 's';   // 's' = enviar email antes de expirar
$DIAS_NOTIFICACAO    = 30;    // Avisar X dias antes
// ============================================

$tenants = Tenant::all();

foreach ($tenants as $tenant) {
    echo "\n--- Configurando Tenant: {$tenant->id} ---\n";
    $tenant->run(function() use ($DIAS_EXPIRACAO, $EXPIRACAO_ATIVA, $NOTIFICACAO_ATIVA, $DIAS_NOTIFICACAO) {

        $opcoes = [
            'pontos_expiracao_ativa'   => $EXPIRACAO_ATIVA,
            'pontos_dias_expiracao'    => (string) $DIAS_EXPIRACAO,
            'pontos_notificacao_ativa' => $NOTIFICACAO_ATIVA,
            'pontos_notificacao_dias'  => (string) $DIAS_NOTIFICACAO,
        ];

        foreach ($opcoes as $chave => $valor) {
            // Tenta encontrar a opção existente e atualiza, senão insere
            $existe = DB::table('system_options')->where('option_key', $chave)->first();
            if ($existe) {
                DB::table('system_options')->where('option_key', $chave)->update(['option_value' => $valor]);
                echo "  Atualizado: {$chave} = {$valor}\n";
            } else {
                DB::table('system_options')->insert(['option_key' => $chave, 'option_value' => $valor]);
                echo "  Inserido:   {$chave} = {$valor}\n";
            }
        }
    });
}

echo "\nPronto! Rode agora:\n";
echo "  php artisan points:migrate-legacy --dry-run\n";
