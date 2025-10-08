<?php

require_once __DIR__ . '/vendor/autoload.php';

// Configurar ambiente Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\User;

echo "=== Verificação da Tabela Users ===\n\n";

try {
    // Verificar colunas da tabela users
    $columns = Schema::getColumnListing('users');
    echo "Colunas da tabela users:\n";
    foreach ($columns as $column) {
        echo "- {$column}\n";
    }
    
    echo "\n";
    
    // Verificar se permission_id existe
    if (in_array('permission_id', $columns)) {
        echo "✅ Coluna 'permission_id' existe na tabela users\n";
    } else {
        echo "❌ Coluna 'permission_id' NÃO existe na tabela users\n";
    }
    
    // Verificar um usuário específico
    $user = User::first();
    if ($user) {
        echo "\nUsuário encontrado: {$user->name}\n";
        echo "ID: {$user->id}\n";
        
        // Verificar se tem permission_id
        if (isset($user->permission_id)) {
            echo "Permission ID: {$user->permission_id}\n";
        } else {
            echo "Permission ID: não definido\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "📍 Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== Fim da verificação ===\n";