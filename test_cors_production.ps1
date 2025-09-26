# Teste CORS para produção

Write-Host "=== TESTE CORS PARA PRODUÇÃO ==="
Write-Host ""

# Simular requisição do frontend de produção
Write-Host "1. Testando requisição simulando frontend de produção..."
try {
    $headers = @{
        "Origin" = "https://clubeyellow.maisaqui.com.br"
        "User-Agent" = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
        "Accept" = "application/json"
        "Content-Type" = "application/json"
    }
    
    $response = Invoke-WebRequest -Uri "http://yellow-dev.localhost:8000/api/v1/user" -Method GET -Headers $headers -UseBasicParsing -ErrorAction SilentlyContinue
    
    Write-Host "   Status: $($response.StatusCode)"
    
    # Verificar headers CORS
    if ($response.Headers["Access-Control-Allow-Origin"]) {
        Write-Host "   OK Access-Control-Allow-Origin: $($response.Headers['Access-Control-Allow-Origin'])"
    } else {
        Write-Host "   ERRO Access-Control-Allow-Origin: NAO ENCONTRADO"
    }
    
    if ($response.Headers["Access-Control-Allow-Credentials"]) {
        Write-Host "   OK Access-Control-Allow-Credentials: $($response.Headers['Access-Control-Allow-Credentials'])"
    } else {
        Write-Host "   ERRO Access-Control-Allow-Credentials: NAO ENCONTRADO"
    }
    
} catch {
    Write-Host "   Erro: $($_.Exception.Message)"
    
    # Tentar capturar headers mesmo em caso de erro
    if ($_.Exception.Response) {
        $errorResponse = $_.Exception.Response
        Write-Host "   Status do erro: $($errorResponse.StatusCode)"
        
        if ($errorResponse.Headers["Access-Control-Allow-Origin"]) {
            Write-Host "   OK CORS presente mesmo com erro: $($errorResponse.Headers['Access-Control-Allow-Origin'])"
        } else {
            Write-Host "   ERRO CORS ausente no erro - PROBLEMA IDENTIFICADO"
        }
    }
}

Write-Host ""

# Teste OPTIONS preflight
Write-Host "2. Testando requisição OPTIONS (preflight)..."
try {
    $headers = @{
        "Origin" = "https://clubeyellow.maisaqui.com.br"
        "Access-Control-Request-Method" = "GET"
        "Access-Control-Request-Headers" = "Content-Type, Authorization"
    }
    
    $response = Invoke-WebRequest -Uri "http://yellow-dev.localhost:8000/api/v1/user" -Method OPTIONS -Headers $headers -UseBasicParsing -ErrorAction SilentlyContinue
    
    Write-Host "   Status: $($response.StatusCode)"
    
    if ($response.Headers["Access-Control-Allow-Origin"]) {
        Write-Host "   OK Access-Control-Allow-Origin: $($response.Headers['Access-Control-Allow-Origin'])"
    } else {
        Write-Host "   ERRO Access-Control-Allow-Origin: NAO ENCONTRADO"
    }
    
} catch {
    Write-Host "   Erro OPTIONS: $($_.Exception.Message)"
}

Write-Host ""
Write-Host "=== DIAGNOSTICO ==="
Write-Host "Se os headers CORS estão ausentes mesmo com erro 500,"
Write-Host "o problema está na configuração do servidor em produção."
Write-Host "Verifique se mod_headers está habilitado no Apache de produção."
Write-Host ""
Write-Host "=== FIM DO TESTE ==="