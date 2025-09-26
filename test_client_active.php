<?php

// Teste para reproduzir o erro de ativação de cliente

$url = 'http://yellow-dev.localhost:8000/api/v1/clients/active';

// Primeiro, gerar um token
$tokenResponse = file_get_contents('http://yellow-dev.localhost:8000/api/v1/public/form-token');
$tokenData = json_decode($tokenResponse, true);
$token = $tokenData['token'];

echo "Token gerado: $token\n";

// Dados do cliente para teste
$clientData = [
    'cpf' => '253.673.140-58',
    'email' => 'suporte1@maisaqui.com.br',
    'name' => 'Fernando Jose Queta',
    'password' => '123456',
    'phone' => '3225665555',
    'privacyAccepted' => true,
    'termsAccepted' => true
];

// Configurar contexto para a requisição POST
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'X-Form-Token: ' . $token,
            'Accept: application/json'
        ],
        'content' => json_encode($clientData)
    ]
]);

// Fazer a requisição
echo "\nFazendo requisição para: $url\n";
echo "Dados enviados: " . json_encode($clientData, JSON_PRETTY_PRINT) . "\n";

$response = file_get_contents($url, false, $context);

if ($response === false) {
    echo "Erro na requisição\n";
    $error = error_get_last();
    echo "Detalhes do erro: " . print_r($error, true) . "\n";
} else {
    echo "\nResposta recebida:\n";
    $responseData = json_decode($response, true);
    echo json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
}