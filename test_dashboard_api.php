<?php

/**
 * Script para testar a API do Dashboard
 * Valida se a rota /api/v1/dashboard retorna os dados corretos
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use App\Http\Controllers\api\DashboardController;
use App\Models\Client;

// Configurar ambiente de teste
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;

echo "🧪 TESTE DA API DO DASHBOARD\n";
echo "============================\n\n";

// Inicializar tenant
$tenant = Tenant::where('id', 'yellow-dev')->first();
if (!$tenant) {
    echo "❌ Tenant 'yellow-dev' não encontrado!\n";
    exit(1);
}

$tenant->run(function () {
    try {
        // Simular request
        $request = Request::create('/api/v1/dashboard', 'GET');
        
        // Instanciar controller
        $controller = new DashboardController();
    
    echo "📊 Testando endpoint /api/v1/dashboard...\n";
    
    // Executar método do controller
    $response = $controller->index($request);
    
    // Verificar se a resposta é JSON
    $data = $response->getData(true);
    
    if (!$data['success']) {
        echo "❌ Erro na resposta: " . $data['message'] . "\n";
        if (isset($data['error'])) {
            echo "   Detalhes: " . $data['error'] . "\n";
        }
        exit(1);
    }
    
    echo "✅ Resposta recebida com sucesso!\n\n";
    
    // Validar estrutura dos dados
    $dashboardData = $data['data'];
    
    echo "📋 VALIDANDO ESTRUTURA DOS DADOS:\n";
    echo "================================\n\n";
    
    // 1. Verificar recentClientActivities
    if (isset($dashboardData['recentClientActivities'])) {
        $activities = $dashboardData['recentClientActivities'];
        echo "✅ recentClientActivities: " . count($activities) . " atividades encontradas\n";
        
        if (count($activities) > 0) {
            $firstActivity = $activities[0];
            $requiredFields = ['id', 'type', 'title', 'client', 'status', 'time'];
            
            foreach ($requiredFields as $field) {
                if (isset($firstActivity[$field])) {
                    echo "   ✅ Campo '$field': " . $firstActivity[$field] . "\n";
                } else {
                    echo "   ❌ Campo '$field' ausente\n";
                }
            }
        }
    } else {
        echo "❌ recentClientActivities não encontrado\n";
    }
    
    echo "\n";
    
    // 2. Verificar clientRegistrationData
    if (isset($dashboardData['clientRegistrationData'])) {
        $registrationData = $dashboardData['clientRegistrationData'];
        echo "✅ clientRegistrationData: " . count($registrationData) . " dias de dados\n";
        
        if (count($registrationData) > 0) {
            $firstDay = $registrationData[0];
            $requiredFields = ['date', 'actived', 'inactived', 'pre_registred'];
            
            foreach ($requiredFields as $field) {
                if (isset($firstDay[$field])) {
                    echo "   ✅ Campo '$field': " . $firstDay[$field] . "\n";
                } else {
                    echo "   ❌ Campo '$field' ausente\n";
                }
            }
        }
    } else {
        echo "❌ clientRegistrationData não encontrado\n";
    }
    
    echo "\n";
    
    // 3. Verificar pendingPreRegistrations
    if (isset($dashboardData['pendingPreRegistrations'])) {
        $pending = $dashboardData['pendingPreRegistrations'];
        echo "✅ pendingPreRegistrations: " . count($pending) . " pré-cadastros pendentes\n";
        
        if (count($pending) > 0) {
            $firstPending = $pending[0];
            $requiredFields = ['id', 'name', 'email', 'date', 'type'];
            
            foreach ($requiredFields as $field) {
                if (isset($firstPending[$field])) {
                    echo "   ✅ Campo '$field': " . $firstPending[$field] . "\n";
                } else {
                    echo "   ❌ Campo '$field' ausente\n";
                }
            }
        }
    } else {
        echo "❌ pendingPreRegistrations não encontrado\n";
    }
    
    echo "\n";
    
    // 4. Verificar totals
    if (isset($dashboardData['totals'])) {
        $totals = $dashboardData['totals'];
        echo "✅ totals encontrado\n";
        
        $requiredFields = ['actived', 'inactived', 'pre_registred', 'variation_percentage'];
        
        foreach ($requiredFields as $field) {
            if (isset($totals[$field])) {
                echo "   ✅ Campo '$field': " . $totals[$field] . "\n";
            } else {
                echo "   ❌ Campo '$field' ausente\n";
            }
        }
    } else {
        echo "❌ totals não encontrado\n";
    }
    
    echo "\n";
    
    // Exibir JSON completo para debug
    echo "📄 DADOS COMPLETOS (JSON):\n";
    echo "==========================\n";
    echo json_encode($dashboardData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // Testar métodos do modelo Client diretamente
    echo "🔧 TESTANDO MÉTODOS DO MODELO CLIENT:\n";
    echo "====================================\n\n";
    
    // Testar scopes
    echo "📊 Contadores por status:\n";
    echo "   Ativos: " . Client::active()->count() . "\n";
    echo "   Inativos: " . Client::inactive()->count() . "\n";
    echo "   Pré-cadastros: " . Client::preRegistered()->count() . "\n";
    echo "   Total: " . Client::count() . "\n\n";
    
    // Testar método de atividades recentes
    $recentActivities = Client::getRecentActivities(5);        
    echo "📋 Atividades recentes (últimas 5): " . count($recentActivities) . " encontradas\n";
    
    // Testar método de dados de registro
    $registrationData = Client::getRegistrationDataByPeriod(7);
    echo "📈 Dados de registro (7 dias): " . count($registrationData) . " dias\n";
    
    // Testar método de totais
    $totals = Client::getDashboardTotals();
    echo "📊 Totais do dashboard: " . json_encode($totals) . "\n\n";
    
    echo "✅ TESTE CONCLUÍDO COM SUCESSO!\n";
    echo "\n";
    echo "🚀 PRÓXIMOS PASSOS:\n";
    echo "==================\n";
    echo "1. Testar via HTTP: GET /api/v1/dashboard\n";
    echo "2. Verificar autenticação Sanctum\n";
    echo "3. Validar CORS se necessário\n";
    echo "4. Integrar com frontend\n";

    } catch (Exception $e) {
        echo "❌ Erro durante o teste: " . $e->getMessage() . "\n";
        echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
        exit(1);
    }
});