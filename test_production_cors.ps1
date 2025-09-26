# Teste do endpoint de produção para CORS
Write-Host "=== TESTE CORS PRODUÇÃO ==="

try {
    # Teste OPTIONS (preflight) primeiro
    Write-Host "1. Testando OPTIONS (preflight)..."
    $optionsHeaders = @{
        "Origin" = "https://clubeyellow.maisaqui.com.br"
        "Access-Control-Request-Method" = "POST"
        "Access-Control-Request-Headers" = "Content-Type,Authorization"
    }
    
    $optionsResponse = Invoke-WebRequest -Uri "https://api-clubeyellow.maisaqui.com.br/api/v1/login" -Method OPTIONS -Headers $optionsHeaders -UseBasicParsing
    
    Write-Host "Status OPTIONS: $($optionsResponse.StatusCode)"
    Write-Host "Headers CORS OPTIONS:"
    
    if ($optionsResponse.Headers["Access-Control-Allow-Origin"]) {
        Write-Host "  OK Access-Control-Allow-Origin: $($optionsResponse.Headers['Access-Control-Allow-Origin'])"
    } else {
        Write-Host "  ERRO Access-Control-Allow-Origin: ausente"
    }
    
    if ($optionsResponse.Headers["Access-Control-Allow-Methods"]) {
        Write-Host "  OK Access-Control-Allow-Methods: $($optionsResponse.Headers['Access-Control-Allow-Methods'])"
    } else {
        Write-Host "  ERRO Access-Control-Allow-Methods: ausente"
    }
    
    if ($optionsResponse.Headers["Access-Control-Allow-Headers"]) {
        Write-Host "  OK Access-Control-Allow-Headers: $($optionsResponse.Headers['Access-Control-Allow-Headers'])"
    } else {
        Write-Host "  ERRO Access-Control-Allow-Headers: ausente"
    }
    
} catch {
    Write-Host "Erro OPTIONS: $($_.Exception.Message)"
    Write-Host "Status Code OPTIONS: $($_.Exception.Response.StatusCode.value__)"
}

Write-Host ""
Write-Host "2. Testando POST (requisição real)..."

try {
    # Teste POST para login
    $headers = @{
        "Content-Type" = "application/json"
        "Origin" = "https://clubeyellow.maisaqui.com.br"
    }
    
    $body = @{
        email = "test@test.com"
        password = "password"
    } | ConvertTo-Json
    
    $response = Invoke-WebRequest -Uri "https://api-clubeyellow.maisaqui.com.br/api/v1/login" -Method POST -Headers $headers -Body $body -UseBasicParsing
    
    Write-Host "Status POST: $($response.StatusCode)"
    Write-Host "Headers CORS POST:"
    
    if ($response.Headers["Access-Control-Allow-Origin"]) {
        Write-Host "  OK Access-Control-Allow-Origin: $($response.Headers['Access-Control-Allow-Origin'])"
    } else {
        Write-Host "  ERRO Access-Control-Allow-Origin: ausente"
    }
    
} catch {
    Write-Host "Erro POST: $($_.Exception.Message)"
    Write-Host "Status Code POST: $($_.Exception.Response.StatusCode.value__)"
    
    # Verificar headers mesmo com erro
    if ($_.Exception.Response) {
        $errorHeaders = $_.Exception.Response.Headers
        Write-Host "Headers CORS no erro POST:"
        
        if ($errorHeaders["Access-Control-Allow-Origin"]) {
            Write-Host "  OK Access-Control-Allow-Origin: $($errorHeaders['Access-Control-Allow-Origin'])"
        } else {
            Write-Host "  ERRO Access-Control-Allow-Origin: ausente"
        }
        
        if ($errorHeaders["Access-Control-Allow-Credentials"]) {
            Write-Host "  OK Access-Control-Allow-Credentials: $($errorHeaders['Access-Control-Allow-Credentials'])"
        } else {
            Write-Host "  ERRO Access-Control-Allow-Credentials: ausente"
        }
    }
}

Write-Host ""
Write-Host "=== DIAGNOSTICO ==="
Write-Host "Se Access-Control-Allow-Origin estiver ausente:"
Write-Host "1. Verificar se .htaccess foi aplicado no servidor"
Write-Host "2. Verificar se mod_headers esta habilitado"
Write-Host "3. Verificar se ha conflitos com outras configuracoes"
Write-Host "=== FIM DO TESTE ==="