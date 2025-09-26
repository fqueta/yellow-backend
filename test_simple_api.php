<?php

// Script simples para testar a API de clientes
echo "=== Teste da API de Clientes ===\n\n";

// 1. Obter token
echo "1. Obtendo token...\n";
$tokenUrl = 'http://yellow-dev.localhost:8000/api/v1/public/form-token';
$tokenResponse = file_get_contents($tokenUrl, false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => '{}'
    ]
]));

if ($tokenResponse === false) {
    echo "Erro ao obter token\n";
    exit(1);
}

$tokenData = json_decode($tokenResponse, true);
if (!isset($tokenData['token'])) {
    echo "Token não encontrado na resposta\n";
    echo "Resposta: " . $tokenResponse . "\n";
    exit(1);
}

$token = $tokenData['token'];
echo "Token obtido: $token\n\n";

// 2. Testar endpoint de clientes
echo "2. Testando endpoint /clients/active...\n";

$clientData = [
    'cpf' => '253.673.140-58',
    'email' => 'joao.teste@example.com',
    'name' => 'João Silva Teste',
    'password' => '123456',
    'phone' => '11999999999',
    'privacyAccepted' => true,
    'termsAccepted' => true,
    '_token' => $token
];

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'X-Form-Token: ' . $token
        ],
        'content' => json_encode($clientData),
        'ignore_errors' => true
    ]
]);

$response = file_get_contents('http://yellow-dev.localhost:8000/api/v1/clients/active', false, $context);

if ($response === false) {
    echo "Erro na requisição\n";
    $error = error_get_last();
    echo "Erro: " . $error['message'] . "\n";
} else {
    echo "Resposta recebida:\n";
    echo $response . "\n";
    
    // Tentar decodificar JSON
    $jsonResponse = json_decode($response, true);
    if ($jsonResponse !== null) {
        echo "\nResposta JSON formatada:\n";
        echo json_encode($jsonResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
}

// Verificar headers de resposta
if (isset($http_response_header)) {
    echo "\nHeaders de resposta:\n";
    foreach ($http_response_header as $header) {
        echo $header . "\n";
    }
}

echo "\n=== Fim do teste ===\n";

?>