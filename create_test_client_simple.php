<?php

require_once 'vendor/autoload.php';

// Configurar o ambiente Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

try {
    // Inicializar o tenant
    tenancy()->initialize('yellow-dev');
    echo "Tenant inicializado: yellow-dev\n";
    
    // Criar cliente diretamente usando o modelo User no contexto do tenant
    $cpf = '25367314058';
    
    // Verificar se já existe
    $existing = User::where('cpf', $cpf)->first();
    
    if ($existing) {
        echo "Cliente já existe com ID: {$existing->id}\n";
        echo "CPF: {$existing->cpf}\n";
        echo "Nome: {$existing->name}\n";
        echo "Email: {$existing->email}\n";
        echo "Status: {$existing->status}\n";
        echo "Permission ID: {$existing->permission_id}\n";
    } else {
        $clientData = [
            'cpf' => $cpf,
            'name' => 'João Silva Teste',
            'email' => 'joao.teste@example.com',
            'password' => Hash::make('123456'),
            'status' => 'pending',
            'permission_id' => 5, // ID do cliente
            'tipo_pessoa' => 'pf',
            'genero' => 'ni',
            'ativo' => 's',
            'verificado' => 'n',
            'excluido' => 'n',
            'deletado' => 'n'
        ];
        
        $user = User::create($clientData);
        echo "Cliente criado com sucesso! ID: {$user->id}\n";
        echo "CPF: {$user->cpf}\n";
        echo "Nome: {$user->name}\n";
        echo "Email: {$user->email}\n";
        echo "Status: {$user->status}\n";
        echo "Permission ID: {$user->permission_id}\n";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
}