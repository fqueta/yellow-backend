# Script PowerShell para testar a correção do CORS
Write-Host "=== TESTE DE CORREÇÃO DO CORS ===" -ForegroundColor Yellow
Write-Host ""

$baseUrl = "http://yellow-dev.localhost:8000/api/v1"
$productionUrl = "https://api-clubeyellow.maisaqui.com.br/api/v1"

function Test-CorsHeaders($Url, $Environment) {
    Write-Host "Testando $Environment ($Url)..." -ForegroundColor Cyan
    
    try {
        Write-Host "1. Obtendo token de formulário..." -ForegroundColor White
        $tokenResponse = Invoke-RestMethod -Uri "$Url/public/form-token" -Method POST -ContentType "application/json"
        
        if ($tokenResponse.token) {
            $tokenPreview = $tokenResponse.token.Substring(0,10)
            Write-Host "   Token obtido: $tokenPreview..." -ForegroundColor Green
            
            Write-Host "2. Testando requisição com x-form-token header..." -ForegroundColor White
            
            $headers = @{
                "Content-Type" = "application/json"
                "Accept" = "application/json"
                "x-form-token" = $tokenResponse.token
            }
            
            $body = @{
                cpf = "25367314058"
                name = "Teste CORS"
                email = "teste@example.com"
                password = "123456"
                password_confirmation = "123456"
            } | ConvertTo-Json
            
            try {
                $response = Invoke-RestMethod -Uri "$Url/clients/active" -Method POST -Headers $headers -Body $body
                Write-Host "   Requisição bem-sucedida! CORS configurado corretamente." -ForegroundColor Green
                $responseJson = $response | ConvertTo-Json -Depth 2
                Write-Host "   Response: $responseJson" -ForegroundColor Gray
            }
            catch {
                $statusCode = "N/A"
                if ($_.Exception.Response) {
                    $statusCode = $_.Exception.Response.StatusCode
                }
                Write-Host "   Erro HTTP $statusCode" -ForegroundColor Red
                
                $errorMessage = $_.Exception.Message
                if ($errorMessage -like "*CORS*" -or $errorMessage -like "*cross-origin*" -or $errorMessage -like "*x-form-token*") {
                    Write-Host "   Problema de CORS detectado!" -ForegroundColor Red
                } else {
                    Write-Host "   Erro não relacionado ao CORS (pode ser normal)" -ForegroundColor Yellow
                }
                Write-Host "   Detalhes: $errorMessage" -ForegroundColor Gray
            }
        } else {
            Write-Host "   Falha ao obter token" -ForegroundColor Red
        }
    }
    catch {
        Write-Host "   Erro ao obter token: $($_.Exception.Message)" -ForegroundColor Red
    }
    
    Write-Host ""
}

# Testar ambiente local
Test-CorsHeaders $baseUrl "Local"

# Testar ambiente de produção  
Test-CorsHeaders $productionUrl "Produção"

Write-Host "=== FIM DO TESTE ===" -ForegroundColor Yellow