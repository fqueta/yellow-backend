#!/bin/bash

# Script para diagnosticar erro 500 no Laravel
# Criado para identificar a causa do erro que está impedindo o CORS

echo "=== DIAGNÓSTICO ERRO 500 LARAVEL ==="
echo "Data: $(date)"
echo ""

# 1. Verificar estrutura do projeto
echo "1. Verificando estrutura do projeto..."
echo "-----------------------------------"
if [ -f "artisan" ]; then
    echo "✅ Projeto Laravel encontrado"
else
    echo "❌ Arquivo artisan não encontrado"
    exit 1
fi

# 2. Verificar permissões críticas
echo ""
echo "2. Verificando permissões..."
echo "----------------------------"
echo "📋 Permissões storage/: $(ls -ld storage/ 2>/dev/null || echo 'Não encontrado')"
echo "📋 Permissões bootstrap/cache/: $(ls -ld bootstrap/cache/ 2>/dev/null || echo 'Não encontrado')"
echo "📋 Permissões .env: $(ls -l .env 2>/dev/null || echo 'Arquivo .env não encontrado')"

# 3. Verificar arquivo .env
echo ""
echo "3. Verificando configuração .env..."
echo "----------------------------------"
if [ -f ".env" ]; then
    echo "✅ Arquivo .env encontrado"
    echo "📋 APP_ENV: $(grep '^APP_ENV=' .env 2>/dev/null || echo 'Não definido')"
    echo "📋 APP_DEBUG: $(grep '^APP_DEBUG=' .env 2>/dev/null || echo 'Não definido')"
    echo "📋 APP_KEY: $(grep '^APP_KEY=' .env 2>/dev/null | cut -c1-20 || echo 'Não definido')..."
    echo "📋 DB_CONNECTION: $(grep '^DB_CONNECTION=' .env 2>/dev/null || echo 'Não definido')"
else
    echo "❌ Arquivo .env não encontrado"
    echo "🔧 Copiando .env.example para .env..."
    cp .env.example .env 2>/dev/null && echo "✅ .env criado" || echo "❌ Falha ao criar .env"
fi

# 4. Verificar logs do Laravel
echo ""
echo "4. Verificando logs do Laravel..."
echo "--------------------------------"
LOG_FILE="storage/logs/laravel.log"
if [ -f "$LOG_FILE" ]; then
    echo "✅ Log do Laravel encontrado"
    echo "📋 Últimas 10 linhas do log:"
    echo "----------------------------"
    tail -n 10 "$LOG_FILE" 2>/dev/null || echo "Erro ao ler log"
    echo "----------------------------"
    echo ""
    echo "📋 Erros recentes (últimas 20 linhas com 'ERROR'):"
    echo "--------------------------------------------------"
    tail -n 100 "$LOG_FILE" 2>/dev/null | grep -i "error" | tail -n 20 || echo "Nenhum erro encontrado"
else
    echo "❌ Log do Laravel não encontrado em $LOG_FILE"
fi

# 5. Verificar logs do Apache
echo ""
echo "5. Verificando logs do Apache..."
echo "-------------------------------"
APACHE_ERROR_LOGS=(
    "/var/log/apache2/error.log"
    "/var/log/httpd/error_log"
    "/usr/local/apache2/logs/error_log"
    "/home/maisaqu/logs/error.log"
    "/home/maisaqu/public_html/logs/error.log"
)

for log in "${APACHE_ERROR_LOGS[@]}"; do
    if [ -f "$log" ]; then
        echo "✅ Log Apache encontrado: $log"
        echo "📋 Últimas 5 linhas:"
        tail -n 5 "$log" 2>/dev/null || echo "Erro ao ler log"
        echo "----------------------------"
        break
    fi
done

# 6. Testar configuração do Laravel
echo ""
echo "6. Testando configuração do Laravel..."
echo "-------------------------------------"
echo "🔄 Testando php artisan config:cache..."
php artisan config:cache 2>&1 && echo "✅ Config cache OK" || echo "❌ Erro no config cache"

echo "🔄 Testando php artisan route:list..."
php artisan route:list | grep -i "api/v1/login" 2>/dev/null && echo "✅ Rota /api/v1/login encontrada" || echo "❌ Rota /api/v1/login não encontrada"

# 7. Verificar dependências do Composer
echo ""
echo "7. Verificando dependências..."
echo "-----------------------------"
if [ -f "composer.json" ]; then
    echo "✅ composer.json encontrado"
    if [ -d "vendor" ]; then
        echo "✅ Pasta vendor encontrada"
    else
        echo "❌ Pasta vendor não encontrada"
        echo "🔧 Execute: composer install"
    fi
else
    echo "❌ composer.json não encontrado"
fi

# 8. Teste direto da API
echo ""
echo "8. Teste direto da API..."
echo "------------------------"
echo "🔄 Testando endpoint diretamente..."

# Teste local (bypass Cloudflare)
echo "📋 Teste local (127.0.0.1):"
curl -s -I -X OPTIONS "http://127.0.0.1/api/v1/login" 2>/dev/null | head -n 10 || echo "Falha no teste local"

echo ""
echo "📋 Teste com localhost:"
curl -s -I -X OPTIONS "http://localhost/api/v1/login" 2>/dev/null | head -n 10 || echo "Falha no teste localhost"

# 9. Verificar PHP e extensões
echo ""
echo "9. Verificando PHP..."
echo "-------------------"
echo "📋 Versão PHP: $(php -v | head -n 1 2>/dev/null || echo 'PHP não encontrado')"
echo "📋 Extensões críticas:"
php -m | grep -E "(openssl|pdo|mbstring|tokenizer|xml|ctype|json)" 2>/dev/null || echo "Erro ao verificar extensões"

echo ""
echo "=== RESUMO DO DIAGNÓSTICO ==="
echo "✅ = OK"
echo "❌ = Problema encontrado"
echo "🔧 = Ação necessária"
echo ""
echo "Próximos passos se erro 500 persistir:"
echo "1. 🔧 Verificar e corrigir permissões: chmod -R 755 storage bootstrap/cache"
echo "2. 🔧 Regenerar chave da aplicação: php artisan key:generate"
echo "3. 🔧 Limpar caches: php artisan cache:clear && php artisan config:clear"
echo "4. 🔧 Reinstalar dependências: composer install --no-dev --optimize-autoloader"
echo "5. 🔍 Verificar logs detalhados do Apache e Laravel"
echo "6. 🌐 Verificar configuração do Cloudflare"
echo ""
echo "=== FIM DO DIAGNÓSTICO ==="