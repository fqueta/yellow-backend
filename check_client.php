<?php

require_once 'vendor/autoload.php';

// Carregar configuração do Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;

echo "Verificando cliente com CPF 253.673.140-58...\n";

// Verificar com CPF formatado
$cpfFormatado = '253.673.140-58';
$cpfLimpo = '25367314058';

echo "Buscando com CPF formatado: $cpfFormatado\n";
$client1 = Client::where('cpf', $cpfFormatado)->first();

echo "Buscando com CPF limpo: $cpfLimpo\n";
$client2 = Client::where('cpf', $cpfLimpo)->first();

if ($client1) {
    echo "Cliente encontrado (formatado): " . $client1->name . " - Status: " . $client1->status . "\n";
} elseif ($client2) {
    echo "Cliente encontrado (limpo): " . $client2->name . " - Status: " . $client2->status . "\n";
} else {
    echo "Cliente não encontrado\n";
    echo "Listando alguns clientes existentes:\n";
    $clients = Client::take(5)->get();
    foreach ($clients as $client) {
        echo "- CPF: {$client->cpf}, Nome: {$client->name}, Status: {$client->status}\n";
    }
}