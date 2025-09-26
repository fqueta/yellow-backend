#!/bin/bash

# Script para diagnosticar e corrigir CORS em servidores com Cloudflare
# Versão: 2.0 - Específico para ambientes com proxy/CDN

echo "=== DIAGNÓSTICO CORS - SERVIDOR COM CLOUDFLARE ==="
echo "Data: $(date)"
echo ""

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Função para log colorido
log_success() { echo -e "${GREEN}✅ $1${NC}"; }
log_error() { echo -e "${RED}❌ $1${NC}"; }
log_warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
log_info() { echo -e "${BLUE}📋 $1${NC}"; }
log_action() { echo -e "${YELLOW}🔄 $1${NC}"; }

# Variáveis
PROJECT_ROOT="/home/maisaqu/public_html/yellow-backend"
PUBLIC_DIR="$PROJECT_ROOT/public"
HTACCESS_FILE="$PUBLIC_DIR/.htaccess"
API_URL="https://api-clubeyellow.maisaqui.com.br/api/v1/login"
LOCAL_URL="http://localhost/api/v1/login"
ORIGIN="https://yellow-dev.localhost:3000"

echo "1. Identificando servidor web..."
echo "-----------------------------------"

# Detectar servidor web
APACHE_RUNNING=$(ps aux | grep -E '[a]pache|[h]ttpd' | wc -l)
NGINX_RUNNING=$(ps aux | grep '[n]ginx' | wc -l)
LITESPEED_RUNNING=$(ps aux | grep '[l]itespeed|[l]shttpd' | wc -l)

if [ $APACHE_RUNNING -gt 0 ]; then
    log_success "Apache detectado"
    SERVER_TYPE="apache"
    # Tentar diferentes comandos para Apache
    if command -v apache2ctl >/dev/null 2>&1; then
        APACHE_CMD="apache2ctl"
    elif command -v apachectl >/dev/null 2>&1; then
        APACHE_CMD="apachectl"
    elif command -v httpd >/dev/null 2>&1; then
        APACHE_CMD="httpd"
    else
        APACHE_CMD="unknown"
    fi
    log_info "Comando Apache: $APACHE_CMD"
elif [ $NGINX_RUNNING -gt 0 ]; then
    log_success "Nginx detectado"
    SERVER_TYPE="nginx"
elif [ $LITESPEED_RUNNING -gt 0 ]; then
    log_success "LiteSpeed detectado"
    SERVER_TYPE="litespeed"
else
    log_warning "Servidor web não detectado claramente"
    SERVER_TYPE="unknown"
fi

echo ""
echo "2. Verificando estrutura e .htaccess..."
echo "---------------------------------------"

if [ -d "$PROJECT_ROOT" ]; then
    log_success "Projeto Laravel encontrado: $PROJECT_ROOT"
else
    log_error "Projeto não encontrado em: $PROJECT_ROOT"
    exit 1
fi

if [ -f "$HTACCESS_FILE" ]; then
    log_success "Arquivo .htaccess encontrado"
    log_info "Permissões: $(stat -c '%a' "$HTACCESS_FILE")"
    
    # Verificar conteúdo CORS
    if grep -q "Access-Control-Allow-Origin" "$HTACCESS_FILE"; then
        log_success "Configurações CORS encontradas no .htaccess"
    else
        log_error "Configurações CORS NÃO encontradas no .htaccess"
        log_action "Adicionando configurações CORS..."
        
        # Backup do .htaccess atual
        cp "$HTACCESS_FILE" "$HTACCESS_FILE.backup.$(date +%Y%m%d_%H%M%S)"
        
        # Adicionar configurações CORS
        cat >> "$HTACCESS_FILE" << 'EOF'

# === CONFIGURAÇÕES CORS ===
# Sempre enviar headers CORS, mesmo em erros
Header always set Access-Control-Allow-Origin "https://yellow-dev.localhost:3000"
Header always set Access-Control-Allow-Credentials "true"
Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, Accept, Origin"

# Responder a requisições OPTIONS (preflight)
RewriteEngine On
RewriteCond %{REQUEST_METHOD} OPTIONS
RewriteRule ^(.*)$ $1 [R=200,L]
EOF
        log_success "Configurações CORS adicionadas ao .htaccess"
    fi
else
    log_error "Arquivo .htaccess não encontrado"
    log_action "Criando .htaccess com configurações CORS..."
    
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

# === CONFIGURAÇÕES CORS ===
# Sempre enviar headers CORS, mesmo em erros
Header always set Access-Control-Allow-Origin "https://yellow-dev.localhost:3000"
Header always set Access-Control-Allow-Credentials "true"
Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, Accept, Origin"

# Responder a requisições OPTIONS (preflight)
RewriteCond %{REQUEST_METHOD} OPTIONS
RewriteRule ^(.*)$ $1 [R=200,L]
EOF
    
    chmod 644 "$HTACCESS_FILE"
    log_success ".htaccess criado com configurações CORS"
fi

echo ""
echo "3. Verificando módulos do servidor..."
echo "------------------------------------"

if [ "$SERVER_TYPE" = "apache" ]; then
    # Verificar módulos Apache
    if [ "$APACHE_CMD" != "unknown" ]; then
        if $APACHE_CMD -M 2>/dev/null | grep -q rewrite; then
            log_success "mod_rewrite está habilitado"
        else
            log_error "mod_rewrite NÃO está habilitado"
            log_action "Tentando habilitar mod_rewrite..."
            sudo a2enmod rewrite 2>/dev/null || log_warning "Não foi possível habilitar automaticamente"
        fi
        
        if $APACHE_CMD -M 2>/dev/null | grep -q headers; then
            log_success "mod_headers está habilitado"
        else
            log_error "mod_headers NÃO está habilitado"
            log_action "Tentando habilitar mod_headers..."
            sudo a2enmod headers 2>/dev/null || log_warning "Não foi possível habilitar automaticamente"
        fi
    else
        log_warning "Comando Apache não encontrado, verificação manual necessária"
    fi
elif [ "$SERVER_TYPE" = "nginx" ]; then
    log_info "Nginx detectado - .htaccess não é usado"
    log_warning "Configuração CORS deve ser feita no arquivo de configuração do Nginx"
elif [ "$SERVER_TYPE" = "litespeed" ]; then
    log_info "LiteSpeed detectado - .htaccess deve funcionar"
else
    log_warning "Servidor web não identificado - verificação manual necessária"
fi

echo ""
echo "4. Testando endpoint localmente (bypass Cloudflare)..."
echo "-----------------------------------------------------"

# Testar localmente primeiro
log_action "Testando endpoint local..."
LOCAL_TEST=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost/yellow-backend/public/index.php" 2>/dev/null)
if [ "$LOCAL_TEST" = "200" ] || [ "$LOCAL_TEST" = "404" ]; then
    log_success "Servidor web local respondendo (código: $LOCAL_TEST)"
else
    log_warning "Servidor web local não responde adequadamente (código: $LOCAL_TEST)"
fi

# Testar API local
log_action "Testando API local..."
LOCAL_API_RESPONSE=$(curl -s -i -X OPTIONS \
    -H "Origin: $ORIGIN" \
    "http://localhost/yellow-backend/public/api/v1/login" 2>/dev/null)

if echo "$LOCAL_API_RESPONSE" | grep -q "Access-Control-Allow-Origin"; then
    log_success "Headers CORS funcionando localmente"
else
    log_error "Headers CORS NÃO funcionando localmente"
    log_info "Resposta local:"
    echo "$LOCAL_API_RESPONSE" | head -10
fi

echo ""
echo "5. Verificando configuração do Cloudflare..."
echo "--------------------------------------------"

# Testar através do Cloudflare
log_action "Testando através do Cloudflare..."
CF_RESPONSE=$(curl -s -i -X OPTIONS \
    -H "Origin: $ORIGIN" \
    "$API_URL" 2>/dev/null)

echo "Resposta do Cloudflare:"
echo "$CF_RESPONSE" | head -15
echo ""

if echo "$CF_RESPONSE" | grep -q "cf-ray"; then
    log_info "Requisição passou pelo Cloudflare"
else
    log_warning "Cloudflare não detectado na resposta"
fi

if echo "$CF_RESPONSE" | grep -q "Access-Control-Allow-Origin"; then
    log_success "Headers CORS presentes na resposta do Cloudflare"
else
    log_error "Headers CORS AUSENTES na resposta do Cloudflare"
fi

echo ""
echo "6. Recarregando servidor web..."
echo "-------------------------------"

if [ "$SERVER_TYPE" = "apache" ] && [ "$APACHE_CMD" != "unknown" ]; then
    log_action "Recarregando Apache..."
    sudo systemctl reload apache2 2>/dev/null || \
    sudo systemctl reload httpd 2>/dev/null || \
    sudo service apache2 reload 2>/dev/null || \
    sudo service httpd reload 2>/dev/null || \
    log_warning "Não foi possível recarregar Apache automaticamente"
elif [ "$SERVER_TYPE" = "nginx" ]; then
    log_action "Recarregando Nginx..."
    sudo systemctl reload nginx 2>/dev/null || \
    sudo service nginx reload 2>/dev/null || \
    log_warning "Não foi possível recarregar Nginx automaticamente"
else
    log_warning "Recarga automática não disponível para este servidor"
fi

echo ""
echo "7. Teste final..."
echo "----------------"

sleep 3
log_action "Testando endpoint final..."

FINAL_RESPONSE=$(curl -s -i -X OPTIONS \
    -H "Origin: $ORIGIN" \
    "$API_URL" 2>/dev/null)

echo "Resposta final:"
echo "$FINAL_RESPONSE" | head -15
echo ""

# Verificar headers CORS na resposta final
if echo "$FINAL_RESPONSE" | grep -q "Access-Control-Allow-Origin"; then
    log_success "✅ CORS FUNCIONANDO! Headers encontrados."
else
    log_error "❌ CORS AINDA NÃO FUNCIONANDO"
fi

echo ""
echo "=== RESUMO E PRÓXIMOS PASSOS ==="
echo "✅ = Funcionando"
echo "❌ = Precisa correção"
echo "⚠️  = Atenção necessária"
echo ""
echo "Se CORS ainda não funcionar:"
echo "1. 🔧 Configurar headers CORS no painel do Cloudflare"
echo "2. 🔍 Verificar logs detalhados: tail -f $PROJECT_ROOT/storage/logs/laravel.log"
echo "3. 🌐 Considerar usar Cloudflare Workers para CORS"
echo "4. 📞 Contatar administrador do servidor"
echo "5. 📋 Consultar: CORS_CLOUDFLARE_FIX.md"
echo ""
echo "=== FIM DO DIAGNÓSTICO ==="