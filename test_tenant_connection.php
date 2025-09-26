<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

try {
    echo "Testando conexões de banco...\n\n";
    
    // Testar conexão padrão
    echo "=== Conexão Padrão (mysql) ===\n";
    $tables = DB::connection('mysql')->select('SHOW TABLES');
    echo "Tabelas encontradas na conexão mysql: " . count($tables) . "\n";
    
    // Verificar se existe tabela options na conexão mysql
    $optionsExists = false;
    foreach($tables as $table) {
        $tableName = array_values((array)$table)[0];
        if($tableName === 'options') {
            $optionsExists = true;
            break;
        }
    }
    echo "Tabela 'options' existe na conexão mysql: " . ($optionsExists ? 'SIM' : 'NÃO') . "\n\n";
    
    // Testar conexão de tenant
    echo "=== Conexão Tenant ===\n";
    $tenant = Tenant::find('yellow-dev');
    if($tenant) {
        $tenant->run(function () {
            $tables = DB::select('SHOW TABLES');
            echo "Tabelas encontradas na conexão tenant: " . count($tables) . "\n";
            
            // Verificar se existe tabela options na conexão tenant
            $optionsExists = false;
            foreach($tables as $table) {
                $tableName = array_values((array)$table)[0];
                if($tableName === 'options') {
                    $optionsExists = true;
                    break;
                }
            }
            echo "Tabela 'options' existe na conexão tenant: " . ($optionsExists ? 'SIM' : 'NÃO') . "\n";
            
            if($optionsExists) {
                $count = DB::table('options')->count();
                echo "Número de registros na tabela options: $count\n";
            }
        });
    } else {
        echo "Tenant 'yellow-dev' não encontrado\n";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}