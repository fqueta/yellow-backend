<?php

require_once 'vendor/autoload.php';

// Carregar configuração do Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use Illuminate\Support\Facades\Hash;

echo "Criando cliente de teste...\n";

try {
    // Verificar se já existe
    $cpf = '25367314058';
    $existingClient = Client::where('cpf', $cpf)->first();
    
    if ($existingClient) {
        echo "Cliente já existe: {$existingClient->name} - Status: {$existingClient->status}\n";
    } else {
        // Criar novo cliente
        $client = Client::create([
            'name' => 'João Silva Teste',
            'email' => 'joao.teste@example.com',
            'cpf' => $cpf,
            'password' => Hash::make('123456'),
            'status' => 'pre_registred',
            'tipo_pessoa' => 'pf',
            'genero' => 'ni',
            'ativo' => 'n',
            'excluido' => 'n',
            'deletado' => 'n'
        ]);
        
        echo "Cliente criado com sucesso: {$client->name} - ID: {$client->id}\n";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}