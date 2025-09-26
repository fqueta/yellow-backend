#!/bin/bash

# Script de correção automática CORS para produção
# Execute este script no servidor de produção para diagnosticar e corrigir problemas CORS

echo "=== DIAGNÓSTICO E CORREÇÃO CORS - PRODUÇÃO ==="
echo "Data: $(date)"
echo ""

# Função para verificar se um comando existe
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Função para verificar se um módulo Apache está habilitado
check_apache_module() {
    local module="$1"
    if command_exists apache2ctl; then
        apache2ctl -M 2>/dev/null | grep -q "${module}_module"
    elif command_exists httpd; then
        httpd -M 2>/dev/null | grep -q "${module}_module"
    else
        echo "❌ Apache não encontrado"
        return 1
    fi
}

# 1. Verificar estrutura do projeto
echo "1. Verificando estrutura do projeto..."
echo "-----------------------------------"

if [ -f "public/index.php" ]; then
    echo "✅ Estrutura Laravel encontrada"
    PROJECT_ROOT=$(pwd)
    PUBLIC_DIR="$PROJECT_ROOT/public"
else
    echo "❌ Estrutura Laravel não encontrada"
    echo "Execute este script na raiz do projeto Laravel"
    exit 1
fi

# 2. Verificar arquivo .htaccess
echo ""
echo "2. Verificando arquivo .htaccess..."
echo "----------------------------------"

HTACCESS_FILE="$PUBLIC_DIR/.htaccess"

if [ -f "$HTACCESS_FILE" ]; then
    echo "✅ Arquivo .htaccess encontrado: $HTACCESS_FILE"
    
    # Verificar permissões
    PERMISSIONS=$(stat -c "%a" "$HTACCESS_FILE" 2>/dev/null || stat -f "%A" "$HTACCESS_FILE" 2>/dev/null)
    echo "   Permissões: $PERMISSIONS"
    
    # Verificar se contém configurações CORS
    if grep -q "Access-Control-Allow-Origin" "$HTACCESS_FILE"; then
        echo "✅ Configurações CORS encontradas no .htaccess"
    else
        echo "❌ Configurações CORS NÃO encontradas no .htaccess"
        echo "   O arquivo .htaccess precisa ser atualizado"
    fi
else
    echo "❌ Arquivo .htaccess NÃO encontrado: $HTACCESS_FILE"
    echo "   Criando arquivo .htaccess com configurações CORS..."
    
    # Criar .htaccess com configurações CORS
    cat > "$HTACCESS_FILE" << 'EOF'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

<IfModule mod_headers.c>
    # Definir origens permitidas
    SetEnvIf Origin "^https://yellow-dev\.localhost(:[0-9]+)?$" CORS_ALLOW_ORIGIN=$0
    SetEnvIf Origin "^https://clubeyellow\.maisaqui\.com\.br$" CORS_ALLOW_ORIGIN=$0
    SetEnvIf Origin "^https://api-clubeyellow\.maisaqui\.com\.br$" CORS_ALLOW_ORIGIN=$0

    # Headers CORS sempre enviados, mesmo em caso de erro
    Header always set Access-Control-Allow-Origin "%{CORS_ALLOW_ORIGIN}e" env=CORS_ALLOW_ORIGIN
    Header always set Access-Control-Allow-Credentials "true"
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"
    Header always set Access-Control-Max-Age "86400"

    # Responder a requisições OPTIONS (preflight)
    RewriteCond %{REQUEST_METHOD} OPTIONS
    RewriteRule ^(.*)$ $1 [R=200,L]

    # Headers específicos para rotas da API
    <LocationMatch "^/api/">
        Header always set Access-Control-Allow-Origin "%{CORS_ALLOW_ORIGIN}e" env=CORS_ALLOW_ORIGIN
        Header always set Access-Control-Allow-Credentials "true"
    </LocationMatch>

    # Limitar métodos HTTP permitidos
    <LimitExcept GET POST PUT DELETE OPTIONS>
        Require all denied
    </LimitExcept>
</IfModule>
EOF
    
    chmod 644 "$HTACCESS_FILE"
    echo "✅ Arquivo .htaccess criado com configurações CORS"
fi

# 3. Verificar módulos Apache
echo ""
echo "3. Verificando módulos Apache..."
echo "-------------------------------"

if check_apache_module "rewrite"; then
    echo "✅ mod_rewrite está habilitado"
else
    echo "❌ mod_rewrite NÃO está habilitado"
    echo "   Execute: sudo a2enmod rewrite"
fi

if check_apache_module "headers"; then
    echo "✅ mod_headers está habilitado"
else
    echo "❌ mod_headers NÃO está habilitado"
    echo "   Execute: sudo a2enmod headers"
fi

# 4. Verificar configuração do VirtualHost
echo ""
echo "4. Verificando configuração do VirtualHost..."
echo "--------------------------------------------"

# Procurar arquivos de configuração do Apache
APACHE_CONFIGS=(
    "/etc/apache2/sites-available/*.conf"
    "/etc/httpd/conf.d/*.conf"
    "/etc/apache2/apache2.conf"
    "/etc/httpd/conf/httpd.conf"
)

ALLOW_OVERRIDE_FOUND=false

for config_pattern in "${APACHE_CONFIGS[@]}"; do
    for config_file in $config_pattern; do
        if [ -f "$config_file" ]; then
            if grep -q "AllowOverride All" "$config_file"; then
                echo "✅ AllowOverride All encontrado em: $config_file"
                ALLOW_OVERRIDE_FOUND=true
            fi
        fi
    done
done

if [ "$ALLOW_OVERRIDE_FOUND" = false ]; then
    echo "❌ AllowOverride All NÃO encontrado"
    echo "   Adicione 'AllowOverride All' na configuração do VirtualHost"
fi

# 5. Verificar logs de erro
echo ""
echo "5. Verificando logs de erro recentes..."
echo "-------------------------------------"

LOG_FILES=(
    "/var/log/apache2/error.log"
    "/var/log/httpd/error_log"
    "$PROJECT_ROOT/storage/logs/laravel.log"
)

for log_file in "${LOG_FILES[@]}"; do
    if [ -f "$log_file" ]; then
        echo "📋 Últimas 5 linhas de erro em $log_file:"
        tail -5 "$log_file" | grep -E "(error|Error|ERROR|500)" || echo "   Nenhum erro recente encontrado"
        echo ""
    fi
done

# 6. Testar configuração Apache
echo "6. Testando configuração Apache..."
echo "---------------------------------"

if command_exists apache2ctl; then
    if apache2ctl configtest 2>/dev/null; then
        echo "✅ Configuração Apache válida"
    else
        echo "❌ Erro na configuração Apache"
        apache2ctl configtest
    fi
elif command_exists httpd; then
    if httpd -t 2>/dev/null; then
        echo "✅ Configuração Apache válida"
    else
        echo "❌ Erro na configuração Apache"
        httpd -t
    fi
fi

# 7. Recarregar Apache se necessário
echo ""
echo "7. Recarregando Apache..."
echo "------------------------"

if command_exists systemctl; then
    if systemctl is-active --quiet apache2; then
        echo "🔄 Recarregando Apache (apache2)..."
        sudo systemctl reload apache2
    elif systemctl is-active --quiet httpd; then
        echo "🔄 Recarregando Apache (httpd)..."
        sudo systemctl reload httpd
    fi
elif command_exists service; then
    echo "🔄 Recarregando Apache..."
    sudo service apache2 reload 2>/dev/null || sudo service httpd reload 2>/dev/null
fi

# 8. Teste final
echo ""
echo "8. Executando teste final..."
echo "---------------------------"

echo "Aguardando 3 segundos para o Apache processar as mudanças..."
sleep 3

echo "Testando endpoint novamente..."
curl -s -I -X OPTIONS \
  -H "Origin: https://yellow-dev.localhost:3000" \
  -H "Access-Control-Request-Method: POST" \
  "https://api-clubeyellow.maisaqui.com.br/api/v1/login" | head -10

echo ""
echo "=== RESUMO ==="
echo "✅ = Configurado corretamente"
echo "❌ = Precisa de correção"
echo "🔄 = Ação executada"
echo ""
echo "Se o problema persistir:"
echo "1. Verifique se o domínio está correto no .htaccess"
echo "2. Verifique se não há conflitos com Cloudflare"
echo "3. Verifique logs detalhados do Apache e Laravel"
echo "4. Contate o administrador do servidor"
echo ""
echo "=== FIM DO DIAGNÓSTICO ==="