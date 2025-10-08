<?php

require_once __DIR__ . '/vendor/autoload.php';

// Configurar ambiente Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Point;
use App\Models\User;
use App\Http\Controllers\api\PointController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

echo "=== Teste de Correção do Erro Points Extracts ===\n\n";

try {
    // Buscar um usuário existente
    $user = User::first();
    if (!$user) {
        echo "❌ Nenhum usuário encontrado\n";
        exit(1);
    }
    
    echo "✅ Usuário encontrado: {$user->name} (ID: {$user->id})\n";
    
    // Verificar se existem pontos para este usuário
    $pointsCount = Point::where('client_id', $user->id)->count();
    echo "📊 Pontos encontrados para o usuário: {$pointsCount}\n";
    
    // Se não houver pontos, criar alguns para teste
    if ($pointsCount === 0) {
        echo "🔧 Criando pontos de teste...\n";
        
        Point::create([
            'client_id' => $user->id,
            'tipo' => 'credito',
            'valor' => 100,
            'description' => 'Pontos de teste - crédito',
            'origem' => 'teste',
            'status' => 'ativo',
            'data_expiracao' => now()->addDays(30),
            'created_at' => now()->subDays(2)
        ]);
        
        Point::create([
            'client_id' => $user->id,
            'tipo' => 'debito',
            'valor' => 50,
            'description' => 'Pontos de teste - débito',
            'origem' => 'teste',
            'status' => 'ativo',
            'created_at' => now()->subDays(1)
        ]);
        
        echo "✅ Pontos de teste criados\n";
    }
    
    // Simular requisição para a API
    $request = new Request([
        'page' => 1,
        'per_page' => 10,
        'sort' => 'created_at',
        'order' => 'desc'
    ]);
    
    // Mock do PermissionService para evitar erro de permissão
    $mockPermissionService = new class {
        public function isHasPermission($user, $permission) {
            return true;
        }
    };
    
    // Testar diretamente a lógica do método getPointsExtracts
    echo "\n🧪 Testando lógica de cálculo de saldo...\n";
    
    $points = Point::with(['cliente', 'usuario'])
        ->orderBy('created_at', 'desc')
        ->paginate(10);
    
    $controller = new PointController();
    
    // Testar o primeiro ponto
    if ($points->count() > 0) {
        $firstPoint = $points->first();
        echo "📝 Testando ponto ID: {$firstPoint->id}\n";
        
        // Usar reflexão para acessar métodos privados
        $reflection = new ReflectionClass($controller);
        $calculateBalanceBeforeMethod = $reflection->getMethod('calculateBalanceBefore');
        $calculateBalanceBeforeMethod->setAccessible(true);
        
        $calculateBalanceAfterMethod = $reflection->getMethod('calculateBalanceAfter');
        $calculateBalanceAfterMethod->setAccessible(true);
        
        try {
            $balanceBefore = $calculateBalanceBeforeMethod->invoke($controller, $firstPoint);
            $balanceAfter = $calculateBalanceAfterMethod->invoke($controller, $firstPoint);
            
            echo "✅ Saldo antes: {$balanceBefore}\n";
            echo "✅ Saldo depois: {$balanceAfter}\n";
            echo "✅ Erro corrigido com sucesso!\n";
            
        } catch (Exception $e) {
            echo "❌ Erro ainda presente: " . $e->getMessage() . "\n";
            echo "📍 Linha: " . $e->getLine() . "\n";
        }
    }
    
    echo "\n🎉 Teste concluído!\n";
    
} catch (Exception $e) {
    echo "❌ Erro durante o teste: " . $e->getMessage() . "\n";
    echo "📍 Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}