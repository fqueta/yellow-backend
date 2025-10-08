<?php

/**
 * Script de teste para verificar a rota de listagem de resgates
 * Testa a nova rota GET /api/products/redemptions via HTTP
 */

echo "=== TESTE DA API DE LISTAGEM DE RESGATES ===\n\n";

// URL da API
$url = 'http://yellow-dev.localhost:8000/api/v1/products/redemptions';

// Headers para a requisição
$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
    // Adicione aqui o token de autenticação se necessário
    // 'Authorization: Bearer YOUR_TOKEN_HERE'
];

echo "🌐 Testando URL: {$url}\n";
echo "📡 Fazendo requisição GET...\n\n";

// Inicializar cURL
$ch = curl_init();

// Configurar cURL
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

// Executar requisição
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

// Fechar cURL
curl_close($ch);

// Verificar se houve erro na requisição
if ($error) {
    echo "❌ Erro na requisição cURL: {$error}\n";
    exit(1);
}

echo "📊 Status HTTP: {$httpCode}\n";

// Decodificar resposta JSON
$data = json_decode($response, true);

if ($httpCode === 200) {
    echo "✅ Requisição bem-sucedida!\n\n";
    
    if ($data && isset($data['data'])) {
        echo "📋 Estrutura da resposta encontrada\n";
        echo "📊 Total de resgates: " . count($data['data']) . "\n\n";
        
        if (!empty($data['data'])) {
            $firstRedemption = $data['data'][0];
            
            echo "--- Exemplo de Resgate ---\n";
            foreach ($firstRedemption as $key => $value) {
                echo "{$key}: {$value}\n";
            }
            echo "\n";
            
            // Verificar estrutura esperada
            $expectedFields = ['id', 'productId', 'productName', 'productImage', 'pointsUsed', 'redemptionDate', 'status', 'trackingCode', 'category'];
            $missingFields = [];
            
            foreach ($expectedFields as $field) {
                if (!isset($firstRedemption[$field])) {
                    $missingFields[] = $field;
                }
            }
            
            if (empty($missingFields)) {
                echo "✅ Estrutura de dados está correta!\n";
            } else {
                echo "⚠️  Campos faltando na estrutura: " . implode(', ', $missingFields) . "\n";
            }
        } else {
            echo "ℹ️  Nenhum resgate encontrado para o usuário\n";
        }
    } else {
        echo "⚠️  Estrutura de resposta inesperada\n";
    }
    
} elseif ($httpCode === 401) {
    echo "🔒 Erro de autenticação (401)\n";
    echo "ℹ️  A rota requer autenticação. Adicione um token válido no cabeçalho Authorization\n";
    
} elseif ($httpCode === 404) {
    echo "❌ Rota não encontrada (404)\n";
    echo "ℹ️  Verifique se a rota foi adicionada corretamente e o servidor está rodando\n";
    
} else {
    echo "❌ Erro na requisição (HTTP {$httpCode})\n";
}

echo "\n📄 Resposta completa:\n";
echo $response . "\n";

echo "\n=== FIM DO TESTE ===\n";