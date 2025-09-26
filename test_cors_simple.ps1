# Teste CORS simples

Write-Host "=== TESTE CORS SIMPLES ==="
Write-Host ""

# Teste na rota principal
Write-Host "Testando rota principal com Origin..."
try {
    $response = Invoke-WebRequest -Uri "http://yellow-dev.localhost:8000/" -Method GET -Headers @{"Origin" = "http://localhost:3000"} -UseBasicParsing
    
    Write-Host "Status: $($response.StatusCode)"
    Write-Host "Headers CORS encontrados:"
    
    if ($response.Headers["Access-Control-Allow-Origin"]) {
        Write-Host "  Access-Control-Allow-Origin: $($response.Headers['Access-Control-Allow-Origin'])"
    } else {
        Write-Host "  Access-Control-Allow-Origin: NAO ENCONTRADO"
    }
    
    if ($response.Headers["Access-Control-Allow-Methods"]) {
        Write-Host "  Access-Control-Allow-Methods: $($response.Headers['Access-Control-Allow-Methods'])"
    } else {
        Write-Host "  Access-Control-Allow-Methods: NAO ENCONTRADO"
    }
    
    if ($response.Headers["Access-Control-Allow-Headers"]) {
        Write-Host "  Access-Control-Allow-Headers: $($response.Headers['Access-Control-Allow-Headers'])"
    } else {
        Write-Host "  Access-Control-Allow-Headers: NAO ENCONTRADO"
    }
    
} catch {
    Write-Host "Erro: $($_.Exception.Message)"
}

Write-Host ""
Write-Host "=== FIM DO TESTE ==="