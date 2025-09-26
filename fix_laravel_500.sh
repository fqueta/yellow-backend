#!/bin/bash

# Script para corrigir automaticamente erro 500 no Laravel
# Foca nas causas mais comuns: permissões, cache, configurações

echo "=== CORREÇÃO AUTOMÁTICA ERRO 500 LARAVEL ==="
echo "Data: $(date)"
echo ""

# Verificar se estamos no diretório correto
if [ ! -f "artisan" ]; then
    echo "❌ Erro: Este não é um projeto Laravel (artisan não encontrado)"
    echo "📋 Execute este script no diretório raiz do projeto Laravel"
    exit 1
fi

echo "✅ Projeto Laravel detectado"
echo ""

# 1. Backup de segurança
echo "1. Criando backup de segurança..."
echo "----------------------------------"
BACKUP_DIR="backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp .env "$BACKUP_DIR/.env.backup" 2>/dev/null && echo "✅ Backup .env criado" || echo "⚠️ .env não encontrado"
cp -r storage/logs "$BACKUP_DIR/logs_backup" 2>/dev/null && echo "✅ Backup logs criado" || echo "⚠️ Logs não encontrados"
echo "📋 Backup salvo em: $BACKUP_DIR"

# 2. Verificar e criar .env se necessário
echo ""
echo "2. Verificando arquivo .env..."
echo "-----------------------------"
if [ ! -f ".env" ]; then
    echo "❌ Arquivo .env não encontrado"
    if [ -f ".env.example" ]; then
        cp .env.example .env
        echo "✅ .env criado a partir do .env.example"
    else
        echo "❌ .env.example também não encontrado"
        echo "🔧 Criando .env básico..."
        cat > .env << 'EOF'
APP_NAME=Laravel
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://maisaqu.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DRIVER=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=null
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=mt1

MIX_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
MIX_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
EOF
        echo "✅ .env básico criado"
    fi
else
    echo "✅ Arquivo .env encontrado"
fi

# 3. Corrigir permissões críticas
echo ""
echo "3. Corrigindo permissões..."
echo "---------------------------"
echo "🔧 Corrigindo permissões do storage..."
chmod -R 755 storage 2>/dev/null && echo "✅ Permissões storage corrigidas" || echo "❌ Erro ao corrigir storage"

echo "🔧 Corrigindo permissões do bootstrap/cache..."
chmod -R 755 bootstrap/cache 2>/dev/null && echo "✅ Permissões bootstrap/cache corrigidas" || echo "❌ Erro ao corrigir bootstrap/cache"

echo "🔧 Corrigindo permissões do .env..."
chmod 644 .env 2>/dev/null && echo "✅ Permissões .env corrigidas" || echo "❌ Erro ao corrigir .env"

# Tentar corrigir ownership (pode falhar se não for root)
echo "🔧 Tentando corrigir ownership..."
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null && echo "✅ Ownership corrigido" || echo "⚠️ Ownership não alterado (execute como root se necessário)"

# 4. Gerar chave da aplicação
echo ""
echo "4. Verificando chave da aplicação..."
echo "----------------------------------"
APP_KEY=$(grep '^APP_KEY=' .env | cut -d'=' -f2)
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "❌ APP_KEY vazia, gerando nova chave..."
    php artisan key:generate --force 2>/dev/null && echo "✅ Chave gerada com sucesso" || echo "❌ Erro ao gerar chave"
else
    echo "✅ APP_KEY já configurada"
fi

# 5. Limpar todos os caches
echo ""
echo "5. Limpando caches..."
echo "-------------------"
echo "🔧 Limpando cache de aplicação..."
php artisan cache:clear 2>/dev/null && echo "✅ Cache limpo" || echo "❌ Erro ao limpar cache"

echo "🔧 Limpando cache de configuração..."
php artisan config:clear 2>/dev/null && echo "✅ Config cache limpo" || echo "❌ Erro ao limpar config cache"

echo "🔧 Limpando cache de rotas..."
php artisan route:clear 2>/dev/null && echo "✅ Route cache limpo" || echo "❌ Erro ao limpar route cache"

echo "🔧 Limpando cache de views..."
php artisan view:clear 2>/dev/null && echo "✅ View cache limpo" || echo "❌ Erro ao limpar view cache"

# 6. Verificar e instalar dependências
echo ""
echo "6. Verificando dependências..."
echo "-----------------------------"
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "❌ Dependências não encontradas, instalando..."
    composer install --no-dev --optimize-autoloader 2>/dev/null && echo "✅ Dependências instaladas" || echo "❌ Erro ao instalar dependências"
else
    echo "✅ Dependências encontradas"
    echo "🔧 Otimizando autoloader..."
    composer dump-autoload --optimize 2>/dev/null && echo "✅ Autoloader otimizado" || echo "❌ Erro ao otimizar autoloader"
fi

# 7. Recriar caches otimizados
echo ""
echo "7. Recriando caches otimizados..."
echo "--------------------------------"
echo "🔧 Criando cache de configuração..."
php artisan config:cache 2>/dev/null && echo "✅ Config cache criado" || echo "❌ Erro ao criar config cache"

echo "🔧 Criando cache de rotas..."
php artisan route:cache 2>/dev/null && echo "✅ Route cache criado" || echo "❌ Erro ao criar route cache"

# 8. Verificar estrutura de diretórios
echo ""
echo "8. Verificando estrutura..."
echo "--------------------------"
DIRS=("storage/app" "storage/framework/cache" "storage/framework/sessions" "storage/framework/views" "storage/logs")
for dir in "${DIRS[@]}"; do
    if [ ! -d "$dir" ]; then
        echo "🔧 Criando diretório: $dir"
        mkdir -p "$dir" && chmod 755 "$dir"
    fi
done
echo "✅ Estrutura de diretórios verificada"

# 9. Teste básico da aplicação
echo ""
echo "9. Testando aplicação..."
echo "------------------------"
echo "🔄 Testando artisan..."
php artisan --version 2>/dev/null && echo "✅ Laravel funcionando" || echo "❌ Laravel com problemas"

echo "🔄 Verificando rotas..."
php artisan route:list 2>/dev/null | grep -q "api/v1/login" && echo "✅ Rota login encontrada" || echo "⚠️ Rota login não encontrada"

# 10. Teste HTTP local
echo ""
echo "10. Teste HTTP local..."
echo "----------------------"
echo "🔄 Testando endpoint local..."
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1/api/v1/login" 2>/dev/null)
if [ "$RESPONSE" = "200" ] || [ "$RESPONSE" = "405" ] || [ "$RESPONSE" = "404" ]; then
    echo "✅ Servidor respondendo (código: $RESPONSE)"
else
    echo "❌ Servidor não respondendo ou erro 500 (código: $RESPONSE)"
fi

# Resumo final
echo ""
echo "=== RESUMO DA CORREÇÃO ==="
echo "✅ = Corrigido com sucesso"
echo "❌ = Erro encontrado"
echo "⚠️ = Atenção necessária"
echo ""
echo "Se erro 500 persistir:"
echo "1. 🔍 Verificar logs: tail -f storage/logs/laravel.log"
echo "2. 🔍 Verificar logs Apache: tail -f /var/log/apache2/error.log"
echo "3. 🔧 Verificar configuração do banco de dados no .env"
echo "4. 🔧 Verificar módulos PHP necessários"
echo "5. 🌐 Verificar configuração do Cloudflare"
echo ""
echo "Próximo passo: Executar test_production_cors.sh para verificar CORS"
echo ""
echo "=== FIM DA CORREÇÃO ==="