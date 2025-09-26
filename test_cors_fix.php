<?php

/**
 * Script para testar a correção do CORS e o uso correto da API
 * Este script demonstra como obter um token e fazer a requisição corretamente
 */

// Configurações
$baseUrl = 'https://api-clubeyellow.maisaqui.com.br/api/v1';
$clientData = [
    'cpf' => '25367314058',
    'name' => 'Cliente Teste CORS',
    'email' => 'teste.cors@example.com',
    'password' => '123456',
    'password_confirmation' => '123456'
];

echo "=== TESTE DE CORREÇÃO DO CORS ===\n\n";

// Função para fazer requisições HTTP
function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: CORS-Test-Script/1.0'
        ], $headers)
    ]);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    return [
        'success' => !$error && $httpCode < 400,
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error,
        'data' => $response ? json_decode($response, true) : null
    ];
}

// Passo 1: Obter token de formulário público
echo "1. Obtendo token de formulário público...\n";
$tokenResponse = makeRequest($baseUrl . '/public/form-token', 'POST');

if (!$tokenResponse['success']) {
    echo "❌ Erro ao obter token: {$tokenResponse['error']}\n";
    echo "HTTP Code: {$tokenResponse['http_code']}\n";
    echo "Response: {$tokenResponse['response']}\n";
    exit(1);
}

$tokenData = $tokenResponse['data'];
if (!isset($tokenData['token'])) {
    echo "❌ Token não encontrado na resposta\n";
    echo "Response: {$tokenResponse['response']}\n";
    exit(1);
}

$formToken = $tokenData['token'];
echo "✅ Token obtido com sucesso: " . substr($formToken, 0, 10) . "...\n";
echo "   Expira em: {$tokenData['expires_in']} segundos\n\n";

// Passo 2: Fazer requisição para clients/active com o token
echo "2. Fazendo requisição para clients/active com token...\n";
$headers = [
    'X-Form-Token: ' . $formToken
];

$clientResponse = makeRequest($baseUrl . '/clients/active', 'POST', $clientData, $headers);

echo "HTTP Code: {$clientResponse['http_code']}\n";
echo "Response: {$clientResponse['response']}\n\n";

if ($clientResponse['success']) {
    echo "✅ Requisição realizada com sucesso!\n";
    echo "✅ CORS configurado corretamente - header x-form-token aceito\n";
} else {
    echo "❌ Erro na requisição\n";
    if ($clientResponse['error']) {
        echo "Erro cURL: {$clientResponse['error']}\n";
    }
    
    // Verificar se é erro de CORS
    if (strpos($clientResponse['response'], 'x-form-token') !== false || 
        strpos($clientResponse['response'], 'CORS') !== false ||
        strpos($clientResponse['response'], 'cross-origin') !== false) {
        echo "❌ Ainda há problemas de CORS detectados\n";
    }
}

echo "\n=== FIM DO TESTE ===\n";