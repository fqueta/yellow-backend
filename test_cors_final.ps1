# Teste CORS apos correcao do .htaccess

Write-Host "=== TESTE CORS APOS CORRECAO ==="
Write-Host ""

# Teste 1: Verificar se o servidor local responde com headers CORS
Write-Host "1. Testando headers CORS no servidor local..."
try {
    $response = Invoke-WebRequest -Uri "http://yellow-dev.localhost:8000/api/v1/user" -Method GET -Headers @{"Origin" = "http://localhost:3000"} -UseBasicParsing -ErrorAction SilentlyContinue
    
    Write-Host "   Status: $($response.StatusCode)"
    
    # Verificar headers CORS
    $corsHeaders = @(
        "Access-Control-Allow-Origin",
        "Access-Control-Allow-Methods",
        "Access-Control-Allow-Headers",
        "Access-Control-Allow-Credentials"
    )
    
    foreach ($header in $corsHeaders) {
        if ($response.Headers[$header]) {
            Write-Host "   OK $header - $($response.Headers[$header])"
        } else {
            Write-Host "   ERRO $header - Nao encontrado"
        }
    }
} catch {
    Write-Host "   Erro: $($_.Exception.Message)"
}

Write-Host ""

# Teste 2: Verificar requisicao OPTIONS (preflight)
Write-Host "2. Testando requisicao OPTIONS (preflight)..."
try {
    $response = Invoke-WebRequest -Uri "http://yellow-dev.localhost:8000/api/v1/user" -Method OPTIONS -Headers @{"Origin" = "http://localhost:3000"; "Access-Control-Request-Method" = "GET"; "Access-Control-Request-Headers" = "Content-Type, Authorization"} -UseBasicParsing -ErrorAction SilentlyContinue
    
    Write-Host "   Status: $($response.StatusCode)"
    
    # Verificar headers CORS na resposta OPTIONS
    $corsHeaders = @(
        "Access-Control-Allow-Origin",
        "Access-Control-Allow-Methods",
        "Access-Control-Allow-Headers"
    )
    
    foreach ($header in $corsHeaders) {
        if ($response.Headers[$header]) {
            Write-Host "   OK $header - $($response.Headers[$header])"
        } else {
            Write-Host "   ERRO $header - Nao encontrado"
        }
    }
} catch {
    Write-Host "   Erro: $($_.Exception.Message)"
}

Write-Host ""
Write-Host "=== FIM DO TESTE CORS ==="