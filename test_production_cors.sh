#!/bin/bash

# Script para testar CORS no endpoint de produção
# Testa tanto requisições OPTIONS (preflight) quanto POST

echo "=== Teste de CORS - Produção ==="
echo "Endpoint: https://api-clubeyellow.maisaqui.com.br/api/v1/login"
echo ""

# Função para extrair headers específicos
function extract_header() {
    local response="$1"
    local header="$2"
    echo "$response" | grep -i "$header:" | head -1 | cut -d':' -f2- | sed 's/^ *//'
}

# Teste 1: Requisição OPTIONS (preflight)
echo "1. Testando requisição OPTIONS (preflight)..."
echo "-------------------------------------------"

OPTIONS_RESPONSE=$(curl -s -i -X OPTIONS \
  -H "Origin: https://yellow-dev.localhost:3000" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type" \
  "https://api-clubeyellow.maisaqui.com.br/api/v1/login" 2>&1)

echo "Resposta OPTIONS:"
echo "$OPTIONS_RESPONSE"
echo ""

# Extrair headers CORS da resposta OPTIONS
ACCESS_CONTROL_ALLOW_ORIGIN_OPTIONS=$(extract_header "$OPTIONS_RESPONSE" "Access-Control-Allow-Origin")
ACCESS_CONTROL_ALLOW_METHODS=$(extract_header "$OPTIONS_RESPONSE" "Access-Control-Allow-Methods")
ACCESS_CONTROL_ALLOW_HEADERS=$(extract_header "$OPTIONS_RESPONSE" "Access-Control-Allow-Headers")

echo "Headers CORS encontrados (OPTIONS):"
echo "  Access-Control-Allow-Origin: $ACCESS_CONTROL_ALLOW_ORIGIN_OPTIONS"
echo "  Access-Control-Allow-Methods: $ACCESS_CONTROL_ALLOW_METHODS"
echo "  Access-Control-Allow-Headers: $ACCESS_CONTROL_ALLOW_HEADERS"
echo ""

# Teste 2: Requisição POST
echo "2. Testando requisição POST..."
echo "------------------------------"

POST_RESPONSE=$(curl -s -i -X POST \
  -H "Content-Type: application/json" \
  -H "Origin: https://yellow-dev.localhost:3000" \
  -d '{"email":"test@example.com","password":"password"}' \
  "https://api-clubeyellow.maisaqui.com.br/api/v1/login" 2>&1)

echo "Resposta POST:"
echo "$POST_RESPONSE"
echo ""

# Extrair headers CORS da resposta POST
ACCESS_CONTROL_ALLOW_ORIGIN_POST=$(extract_header "$POST_RESPONSE" "Access-Control-Allow-Origin")
ACCESS_CONTROL_ALLOW_CREDENTIALS=$(extract_header "$POST_RESPONSE" "Access-Control-Allow-Credentials")

echo "Headers CORS encontrados (POST):"
echo "  Access-Control-Allow-Origin: $ACCESS_CONTROL_ALLOW_ORIGIN_POST"
echo "  Access-Control-Allow-Credentials: $ACCESS_CONTROL_ALLOW_CREDENTIALS"
echo ""

# Análise dos resultados
echo "=== DIAGNÓSTICO ==="
echo ""

if [[ -n "$ACCESS_CONTROL_ALLOW_ORIGIN_OPTIONS" || -n "$ACCESS_CONTROL_ALLOW_ORIGIN_POST" ]]; then
    echo "✓ Headers CORS encontrados nas respostas"
else
    echo "✗ Headers CORS NÃO encontrados - Problema de configuração!"
    echo ""
    echo "Possíveis causas:"
    echo "  1. Arquivo .htaccess não foi aplicado corretamente"
    echo "  2. mod_headers não está habilitado no Apache"
    echo "  3. Configuração do Apache não permite AllowOverride All"
    echo "  4. Conflito de configuração no servidor"
fi

echo ""
echo "=== FIM DO TESTE ==="