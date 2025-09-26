#!/bin/bash

# Script para testar CORS com a configuração específica do clubeyellow.maisaqui.com.br
# Valida se o .htaccess está funcionando corretamente

echo "=== TESTE CORS CLUBEYELLOW.MAISAQUI.COM.BR ==="
echo "Data: $(date)"
echo ""

# Configurações
API_URL="https://maisaqu.com/api/v1/login"
ORIGIN="https://clubeyellow.maisaqui.com.br"

echo "🎯 Testando CORS para:"
echo "   API: $API_URL"
echo "   Origin: $ORIGIN"
echo ""

# 1. Teste OPTIONS (Preflight)
echo "1. Teste OPTIONS (Preflight Request)"
echo "====================================="
echo "🔄 Enviando requisição OPTIONS..."

OPTIONS_RESPONSE=$(curl -s -I -X OPTIONS \
  -H "Origin: $ORIGIN" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type, Authorization" \
  "$API_URL" 2>/dev/null)

echo "📋 Resposta completa:"
echo "$OPTIONS_RESPONSE"
echo ""

# Verificar headers CORS específicos
echo "🔍 Verificando headers CORS:"
echo "----------------------------"

# Access-Control-Allow-Origin
ALLOW_ORIGIN=$(echo "$OPTIONS_RESPONSE" | grep -i "access-control-allow-origin" | head -n 1)
if echo "$ALLOW_ORIGIN" | grep -q "clubeyellow.maisaqui.com.br"; then
    echo "✅ Access-Control-Allow-Origin: CORRETO ($ALLOW_ORIGIN)"
else
    echo "❌ Access-Control-Allow-Origin: INCORRETO ou AUSENTE ($ALLOW_ORIGIN)"
fi

# Access-Control-Allow-Methods
ALLOW_METHODS=$(echo "$OPTIONS_RESPONSE" | grep -i "access-control-allow-methods" | head -n 1)
if echo "$ALLOW_METHODS" | grep -q "POST"; then
    echo "✅ Access-Control-Allow-Methods: CORRETO ($ALLOW_METHODS)"
else
    echo "❌ Access-Control-Allow-Methods: INCORRETO ou AUSENTE ($ALLOW_METHODS)"
fi

# Access-Control-Allow-Headers
ALLOW_HEADERS=$(echo "$OPTIONS_RESPONSE" | grep -i "access-control-allow-headers" | head -n 1)
if echo "$ALLOW_HEADERS" | grep -q "Content-Type"; then
    echo "✅ Access-Control-Allow-Headers: CORRETO ($ALLOW_HEADERS)"
else
    echo "❌ Access-Control-Allow-Headers: INCORRETO ou AUSENTE ($ALLOW_HEADERS)"
fi

# Access-Control-Allow-Credentials
ALLOW_CREDENTIALS=$(echo "$OPTIONS_RESPONSE" | grep -i "access-control-allow-credentials" | head -n 1)
if echo "$ALLOW_CREDENTIALS" | grep -q "true"; then
    echo "✅ Access-Control-Allow-Credentials: CORRETO ($ALLOW_CREDENTIALS)"
else
    echo "❌ Access-Control-Allow-Credentials: INCORRETO ou AUSENTE ($ALLOW_CREDENTIALS)"
fi

# Status Code
STATUS_CODE=$(echo "$OPTIONS_RESPONSE" | head -n 1 | grep -o '[0-9]\{3\}')
echo "📊 Status Code: $STATUS_CODE"
if [ "$STATUS_CODE" = "200" ] || [ "$STATUS_CODE" = "204" ]; then
    echo "✅ Status Code: OK"
else
    echo "❌ Status Code: ERRO ($STATUS_CODE)"
fi

echo ""

# 2. Teste POST (Requisição Real)
echo "2. Teste POST (Requisição Real)"
echo "==============================="
echo "🔄 Enviando requisição POST..."

POST_RESPONSE=$(curl -s -I -X POST \
  -H "Origin: $ORIGIN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"test@example.com","password":"test123"}' \
  "$API_URL" 2>/dev/null)

echo "📋 Resposta completa:"
echo "$POST_RESPONSE"
echo ""

# Verificar headers CORS na resposta POST
echo "🔍 Verificando headers CORS na resposta POST:"
echo "---------------------------------------------"

POST_ALLOW_ORIGIN=$(echo "$POST_RESPONSE" | grep -i "access-control-allow-origin" | head -n 1)
if echo "$POST_ALLOW_ORIGIN" | grep -q "clubeyellow.maisaqui.com.br"; then
    echo "✅ POST Access-Control-Allow-Origin: CORRETO ($POST_ALLOW_ORIGIN)"
else
    echo "❌ POST Access-Control-Allow-Origin: INCORRETO ou AUSENTE ($POST_ALLOW_ORIGIN)"
fi

POST_STATUS_CODE=$(echo "$POST_RESPONSE" | head -n 1 | grep -o '[0-9]\{3\}')
echo "📊 POST Status Code: $POST_STATUS_CODE"

echo ""

# 3. Teste com origem não permitida
echo "3. Teste com origem NÃO PERMITIDA"
echo "================================="
echo "🔄 Testando com origem não autorizada..."

UNAUTH_ORIGIN="https://malicious-site.com"
UNAUTH_RESPONSE=$(curl -s -I -X OPTIONS \
  -H "Origin: $UNAUTH_ORIGIN" \
  -H "Access-Control-Request-Method: POST" \
  "$API_URL" 2>/dev/null)

UNAUTH_ALLOW_ORIGIN=$(echo "$UNAUTH_RESPONSE" | grep -i "access-control-allow-origin" | head -n 1)
if echo "$UNAUTH_ALLOW_ORIGIN" | grep -q "clubeyellow.maisaqui.com.br"; then
    echo "✅ Origem não autorizada BLOQUEADA corretamente"
else
    echo "⚠️ Origem não autorizada pode estar sendo aceita: $UNAUTH_ALLOW_ORIGIN"
fi

echo ""

# 4. Teste de conectividade básica
echo "4. Teste de conectividade básica"
echo "================================"
echo "🔄 Testando conectividade com o servidor..."

PING_RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" "https://maisaqu.com" 2>/dev/null)
echo "📊 Conectividade com maisaqu.com: $PING_RESPONSE"

if [ "$PING_RESPONSE" = "200" ] || [ "$PING_RESPONSE" = "301" ] || [ "$PING_RESPONSE" = "302" ]; then
    echo "✅ Servidor acessível"
else
    echo "❌ Problema de conectividade com o servidor"
fi

echo ""

# 5. Verificar se Cloudflare está ativo
echo "5. Verificação Cloudflare"
echo "========================"
echo "🔄 Verificando headers do Cloudflare..."

CF_HEADERS=$(curl -s -I "https://maisaqu.com" 2>/dev/null | grep -i "cf-")
if [ -n "$CF_HEADERS" ]; then
    echo "✅ Cloudflare detectado:"
    echo "$CF_HEADERS"
else
    echo "⚠️ Cloudflare não detectado ou headers não visíveis"
fi

echo ""

# Resumo final
echo "=== RESUMO DO TESTE ==="
echo "✅ = Funcionando corretamente"
echo "❌ = Problema encontrado"
echo "⚠️ = Atenção necessária"
echo ""

# Contadores
SUCCESS_COUNT=0
ERROR_COUNT=0

# Verificar resultados principais
if echo "$ALLOW_ORIGIN" | grep -q "clubeyellow.maisaqui.com.br"; then
    ((SUCCESS_COUNT++))
else
    ((ERROR_COUNT++))
fi

if echo "$ALLOW_METHODS" | grep -q "POST"; then
    ((SUCCESS_COUNT++))
else
    ((ERROR_COUNT++))
fi

if echo "$ALLOW_HEADERS" | grep -q "Content-Type"; then
    ((SUCCESS_COUNT++))
else
    ((ERROR_COUNT++))
fi

if echo "$ALLOW_CREDENTIALS" | grep -q "true"; then
    ((SUCCESS_COUNT++))
else
    ((ERROR_COUNT++))
fi

echo "📊 Resultado geral:"
echo "   ✅ Sucessos: $SUCCESS_COUNT/4"
echo "   ❌ Erros: $ERROR_COUNT/4"

if [ $ERROR_COUNT -eq 0 ]; then
    echo "🎉 CORS configurado PERFEITAMENTE!"
    echo "   A configuração do .htaccess está funcionando corretamente."
elif [ $SUCCESS_COUNT -gt $ERROR_COUNT ]; then
    echo "⚠️ CORS parcialmente funcional"
    echo "   Alguns headers estão corretos, mas há problemas a corrigir."
else
    echo "❌ CORS com PROBLEMAS CRÍTICOS"
    echo "   A configuração precisa ser revisada urgentemente."
fi

echo ""
echo "📋 Próximos passos se houver problemas:"
echo "1. 🔧 Verificar se mod_headers está habilitado no Apache"
echo "2. 🔧 Verificar se AllowOverride All está configurado"
echo "3. 🔧 Verificar logs do Apache: tail -f /var/log/apache2/error.log"
echo "4. 🔧 Testar configuração Apache: apache2ctl configtest"
echo "5. 🌐 Verificar configuração do Cloudflare Transform Rules"
echo "6. 🔄 Recarregar Apache: systemctl reload apache2"
echo ""
echo "=== FIM DO TESTE ==="