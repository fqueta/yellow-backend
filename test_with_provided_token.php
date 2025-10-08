<?php

require_once 'vendor/autoload.php';

// Configurar o ambiente Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TESTE DA API DE RESGATES COM TOKEN ===".PHP_EOL.PHP_EOL;

// Token válido criado para teste
$token = '7|XoNdMFA75N27D7MQHgmePhXNnUh8hAsg7Hx8t2Kde58a9225';

// URL da API usando o domínio do tenant
$url = 'http://yellow-dev.localhost:8000/api/v1/products/redemptions';

echo "🔗 URL: {$url}".PHP_EOL;
echo "🔑 Token: {$token}".PHP_EOL.PHP_EOL;

// Configurar cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

echo "📡 Fazendo requisição...".PHP_EOL;

// Executar requisição
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "📊 Código HTTP: {$httpCode}".PHP_EOL;

if ($error) {
    echo "❌ Erro cURL: {$error}".PHP_EOL;
} else {
    echo "✅ Resposta recebida!".PHP_EOL;
    echo "📄 Conteúdo da resposta:".PHP_EOL;
    echo $response.PHP_EOL;
    
    // Tentar decodificar JSON
    $data = json_decode($response, true);
    if ($data !== null) {
        echo PHP_EOL."🔍 Dados decodificados:".PHP_EOL;
        print_r($data);
    } else {
        echo PHP_EOL."⚠️  Resposta não é um JSON válido".PHP_EOL;
    }
}

echo PHP_EOL."=== FIM DO TESTE ===".PHP_EOL;