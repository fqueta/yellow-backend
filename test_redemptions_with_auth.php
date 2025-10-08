<?php

/**
 * Teste da API de listagem de resgates com autenticação
 * Este script faz login primeiro e depois testa a rota de resgates
 */

echo "=== TESTE DA API DE LISTAGEM DE RESGATES COM AUTENTICAÇÃO ===\n\n";

// Configuração da URL da API com domínio do tenant
$baseUrl = 'http://yellow-dev.localhost:8000';
$loginEndpoint = '/api/v1/login';
$redemptionsEndpoint = '/api/v1/products/redemptions';

// Dados de login (usando usuário existente)
$loginData = [
    'email' => 'test@test.com',
    'password' => 'password'
];

// Headers para as requisições
$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
    'X-Requested-With: XMLHttpRequest'
];

echo "🔐 Fazendo login...\n";

// Fazer login primeiro
$loginCurl = curl_init();
curl_setopt_array($loginCurl, [
    CURLOPT_URL => $baseUrl . $loginEndpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($loginData),
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);

$loginResponse = curl_exec($loginCurl);
$loginHttpCode = curl_getinfo($loginCurl, CURLINFO_HTTP_CODE);
$loginError = curl_error($loginCurl);
curl_close($loginCurl);

if ($loginError) {
    echo "❌ Erro no cURL do login: $loginError\n";
    exit(1);
}

echo "📊 Status HTTP do login: $loginHttpCode\n";

if ($loginHttpCode !== 200) {
    echo "❌ Falha no login (HTTP $loginHttpCode)\n";
    echo "📄 Resposta do login:\n";
    echo $loginResponse . "\n";
    exit(1);
}

$loginData = json_decode($loginResponse, true);

if (!isset($loginData['access_token'])) {
    echo "❌ Token de acesso não encontrado na resposta do login\n";
    echo "📄 Resposta do login:\n";
    echo $loginResponse . "\n";
    exit(1);
}

$token = $loginData['access_token'];
echo "✅ Login realizado com sucesso!\n";
echo "🔑 Token obtido: " . substr($token, 0, 20) . "...\n\n";

// Agora testar a rota de resgates
echo "🌐 Testando URL: $baseUrl$redemptionsEndpoint\n";
echo "📡 Fazendo requisição GET com token...\n";

// Headers com autenticação
$authHeaders = [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $token,
    'X-Requested-With: XMLHttpRequest'
];

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $baseUrl . $redemptionsEndpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $authHeaders,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);
curl_close($curl);

if ($error) {
    echo "❌ Erro no cURL: $error\n";
    exit(1);
}

echo "📊 Status HTTP: $httpCode\n";

// Verificar o status da resposta
switch ($httpCode) {
    case 200:
        echo "✅ Sucesso! Rota funcionando corretamente\n";
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "❌ Erro ao decodificar JSON: " . json_last_error_msg() . "\n";
            echo "📄 Resposta bruta:\n";
            echo $response . "\n";
        } else {
            echo "📋 Estrutura da resposta validada\n";
            
            if (isset($data['data'])) {
                $redemptions = $data['data'];
                echo "📊 Total de resgates encontrados: " . count($redemptions) . "\n";
                
                if (count($redemptions) > 0) {
                    echo "🔍 Exemplo do primeiro resgate:\n";
                    $firstRedemption = $redemptions[0];
                    
                    $expectedFields = ['id', 'pontos', 'status', 'created_at', 'produto'];
                    foreach ($expectedFields as $field) {
                        if (isset($firstRedemption[$field])) {
                            echo "  ✅ Campo '$field': presente\n";
                        } else {
                            echo "  ❌ Campo '$field': ausente\n";
                        }
                    }
                    
                    if (isset($firstRedemption['produto'])) {
                        $produto = $firstRedemption['produto'];
                        $productFields = ['id', 'nome', 'categoria', 'imagem'];
                        echo "  📦 Dados do produto:\n";
                        foreach ($productFields as $field) {
                            if (isset($produto[$field])) {
                                echo "    ✅ Campo '$field': presente\n";
                            } else {
                                echo "    ❌ Campo '$field': ausente\n";
                            }
                        }
                    }
                } else {
                    echo "ℹ️  Nenhum resgate encontrado para este usuário\n";
                }
            } else {
                echo "❌ Campo 'data' não encontrado na resposta\n";
            }
        }
        break;
        
    case 401:
        echo "🔒 Erro de autenticação (401)\n";
        echo "ℹ️  O token pode estar inválido ou expirado\n";
        break;
        
    case 404:
        echo "🔍 Rota não encontrada (404)\n";
        echo "ℹ️  Verifique se a rota foi adicionada corretamente\n";
        break;
        
    case 500:
        echo "💥 Erro interno do servidor (500)\n";
        echo "ℹ️  Verifique os logs do Laravel para mais detalhes\n";
        break;
        
    default:
        echo "⚠️  Status HTTP inesperado: $httpCode\n";
        break;
}

echo "\n📄 Resposta completa:\n";
echo $response . "\n";

echo "\n=== FIM DO TESTE ===\n";