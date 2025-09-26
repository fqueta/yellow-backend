# Teste do endpoint de login local
Write-Host "=== TESTE LOGIN LOCAL ==="

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
    
    Write-Host "Testando POST /api/v1/login..."
    $response = Invoke-WebRequest -Uri "http://yellow-dev.localhost:8000/api/v1/login" -Method POST -Headers $headers -Body $body -UseBasicParsing
    
    Write-Host "Status: $($response.StatusCode)"
    Write-Host "Headers CORS:"
    
    if ($response.Headers["Access-Control-Allow-Origin"]) {
        Write-Host "  OK Access-Control-Allow-Origin: $($response.Headers['Access-Control-Allow-Origin'])"
    } else {
        Write-Host "  ERRO Access-Control-Allow-Origin: ausente"
    }
    
    if ($response.Headers["Access-Control-Allow-Credentials"]) {
        Write-Host "  OK Access-Control-Allow-Credentials: $($response.Headers['Access-Control-Allow-Credentials'])"
    } else {
        Write-Host "  ERRO Access-Control-Allow-Credentials: ausente"
    }
    
    Write-Host "Response Body:"
    Write-Host $response.Content
    
} catch {
    Write-Host "Erro na requisicao: $($_.Exception.Message)"
    Write-Host "Status Code: $($_.Exception.Response.StatusCode.value__)"
    
    # Verificar headers mesmo com erro
    if ($_.Exception.Response) {
        $errorHeaders = $_.Exception.Response.Headers
        Write-Host "Headers CORS no erro:"
        
        if ($errorHeaders["Access-Control-Allow-Origin"]) {
            Write-Host "  OK Access-Control-Allow-Origin: $($errorHeaders['Access-Control-Allow-Origin'])"
        } else {
            Write-Host "  ERRO Access-Control-Allow-Origin: ausente"
        }
    }
}

Write-Host "=== FIM DO TESTE ==="