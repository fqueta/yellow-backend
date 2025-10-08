<?php

require_once 'vendor/autoload.php';

// Configurar o ambiente Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== VERIFICANDO ESTRUTURA DA TABELA REDEMPTIONS ===".PHP_EOL.PHP_EOL;

try {
    // Conectar ao tenant yellow-dev
    $tenant = \App\Models\Tenant::where('id', 'yellow-dev')->first();
    if (!$tenant) {
        echo "❌ Tenant 'yellow-dev' não encontrado!".PHP_EOL;
        exit(1);
    }
    
    $tenant->run(function () {
        echo "🔍 Verificando se a tabela 'redemptions' existe...".PHP_EOL;
        
        if (Schema::hasTable('redemptions')) {
            echo "✅ Tabela 'redemptions' existe!".PHP_EOL.PHP_EOL;
            
            echo "📋 Colunas da tabela 'redemptions':".PHP_EOL;
            $columns = Schema::getColumnListing('redemptions');
            foreach ($columns as $column) {
                echo "  - {$column}".PHP_EOL;
            }
            
            echo PHP_EOL."📊 Detalhes das colunas:".PHP_EOL;
            $columnDetails = DB::select("DESCRIBE redemptions");
            foreach ($columnDetails as $detail) {
                echo "  {$detail->Field}: {$detail->Type} | Null: {$detail->Null} | Default: {$detail->Default}".PHP_EOL;
            }
            
            echo PHP_EOL."📈 Total de registros: " . DB::table('redemptions')->count() . PHP_EOL;
            
        } else {
            echo "❌ Tabela 'redemptions' não existe!".PHP_EOL;
        }
        
        echo PHP_EOL."🔍 Verificando usuários disponíveis...".PHP_EOL;
        $userCount = \App\Models\User::count();
        echo "👥 Total de usuários: {$userCount}".PHP_EOL;
        
        if ($userCount > 0) {
            $firstUser = \App\Models\User::first();
            echo "👤 Primeiro usuário: ID {$firstUser->id} - {$firstUser->name}".PHP_EOL;
        }
        
        echo PHP_EOL."🔍 Verificando produtos disponíveis...".PHP_EOL;
        $productCount = \App\Models\Product::count();
        echo "📦 Total de produtos: {$productCount}".PHP_EOL;
        
        if ($productCount > 0) {
            $firstProduct = \App\Models\Product::first();
            echo "📦 Primeiro produto: ID {$firstProduct->id} - {$firstProduct->titulo}".PHP_EOL;
        }
    });
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . PHP_EOL;
    echo "📍 Arquivo: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
}

echo PHP_EOL."=== FIM DA VERIFICAÇÃO ===".PHP_EOL;