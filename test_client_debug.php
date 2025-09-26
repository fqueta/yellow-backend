<?php

// Gerar token
$tokenResponse = file_get_contents('http://yellow-dev.localhost:8000/api/v1/public/form-token', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
    ]
]));

$tokenData = json_decode($tokenResponse, true);
$token = $tokenData['token'];

echo "Token gerado: $token\n\n";

// Dados do cliente para teste (usando o cliente que criamos)
    $clientData = [
        'cpf' => '253.673.140-58',
        'email' => 'joao.teste@example.com',
        'name' => 'João Silva Teste',
        'password' => '123456',
        'phone' => '11999999999',
        'privacyAccepted' => true,
        'termsAccepted' => true,
        '_token' => $token  // Campo correto para o middleware
    ];

// Fazer requisição para /clients/active
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ],
        'content' => json_encode($clientData)
    ]
]);

$response = @file_get_contents('http://yellow-dev.localhost:8000/api/v1/clients/active', false, $context);

// Capturar headers de resposta sempre
if (isset($http_response_header)) {
    echo "Headers de resposta:\n";
    foreach ($http_response_header as $header) {
        echo $header . "\n";
    }
    echo "\n";
}

// Usar cURL para capturar resposta completa
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://yellow-dev.localhost:8000/api/v1/clients/active');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($clientData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Form-Token: ' . $token  // Header correto para o middleware
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_FAILONERROR, false); // Não falhar em códigos de erro HTTP

$curlResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

echo "\nResposta da API (HTTP $httpCode):\n";

if ($curlError) {
    echo "Erro no cURL: $curlError\n";
} else {
    if (!empty($curlResponse)) {
        echo "Corpo da resposta:\n";
        echo $curlResponse . "\n";
        
        // Tentar decodificar JSON para melhor visualização
        $jsonDecoded = json_decode($curlResponse, true);
        if ($jsonDecoded !== null) {
            echo "\nResposta JSON formatada:\n";
            echo json_encode($jsonDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    } else {
        echo "Corpo da resposta vazio\n";
    }
}

curl_close($ch);

?>