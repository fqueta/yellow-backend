# AÇÕES URGENTES - ERRO 500 LARAVEL

## 🚨 SITUAÇÃO ATUAL
- ✅ Arquivo `.htaccess` com CORS configurado corretamente
- ✅ Servidor Apache detectado
- ❌ **ERRO 500** impedindo funcionamento da API
- ❌ Headers CORS ausentes devido ao erro 500
- ⚠️ Cloudflare interceptando requisições

## 🔧 COMANDOS URGENTES PARA EXECUTAR

### 1. DIAGNÓSTICO COMPLETO (EXECUTAR PRIMEIRO)
```bash
# Copiar e executar o script de diagnóstico
chmod +x debug_500_error.sh
./debug_500_error.sh
```

### 2. CORREÇÕES IMEDIATAS DE PERMISSÕES
```bash
# Corrigir permissões críticas do Laravel
chmod -R 755 storage
chmod -R 755 bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Verificar se .env existe
ls -la .env

# Se .env não existir, criar:
cp .env.example .env
chmod 644 .env
```

### 3. REGENERAR CONFIGURAÇÕES DO LARAVEL
```bash
# Gerar chave da aplicação (se APP_KEY estiver vazio)
php artisan key:generate

# Limpar todos os caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Recriar cache de configuração
php artisan config:cache
```

### 4. VERIFICAR DEPENDÊNCIAS
```bash
# Verificar se vendor existe
ls -la vendor/

# Se vendor não existir ou estiver incompleto:
composer install --no-dev --optimize-autoloader

# Verificar autoload
composer dump-autoload
```

### 5. VERIFICAR LOGS DETALHADOS
```bash
# Ver últimos erros do Laravel
tail -f storage/logs/laravel.log

# Ver erros do Apache (testar diferentes locais)
tail -f /var/log/apache2/error.log
# OU
tail -f /var/log/httpd/error_log
# OU
tail -f /home/maisaqu/logs/error.log
```

### 6. TESTAR CONFIGURAÇÃO PHP
```bash
# Verificar versão e extensões PHP
php -v
php -m | grep -E "(openssl|pdo|mbstring|tokenizer|xml|ctype|json)"

# Testar sintaxe do Laravel
php artisan --version
php artisan route:list | grep login
```

### 7. HABILITAR MÓDULOS APACHE (SE NECESSÁRIO)
```bash
# Verificar módulos habilitados
apache2ctl -M | grep -E "(rewrite|headers)"
# OU
httpd -M | grep -E "(rewrite|headers)"

# Habilitar módulos (se não estiverem ativos)
a2enmod rewrite
a2enmod headers
systemctl reload apache2
# OU
sudo systemctl reload httpd
```

### 8. VERIFICAR VIRTUALHOST
```bash
# Localizar arquivo de configuração do site
find /etc/apache2 -name "*.conf" | grep -i maisaqu
# OU
find /etc/httpd -name "*.conf" | grep -i maisaqu

# Verificar se AllowOverride All está configurado
grep -r "AllowOverride" /etc/apache2/sites-available/
# OU
grep -r "AllowOverride" /etc/httpd/conf.d/
```

## 🌐 CONFIGURAÇÃO CLOUDFLARE (PARALELO)

### Transform Rules (Painel Cloudflare)
1. Acesse: **Rules > Transform Rules > Modify Response Header**
2. Criar regra: **"CORS Headers for API"**
3. **When incoming requests match:**
   - Field: `URI Path`
   - Operator: `starts with`
   - Value: `/api/`

4. **Then modify response header:**
   - `Access-Control-Allow-Origin`: `*`
   - `Access-Control-Allow-Methods`: `GET, POST, PUT, DELETE, OPTIONS`
   - `Access-Control-Allow-Headers`: `Content-Type, Authorization, X-Requested-With`
   - `Access-Control-Allow-Credentials`: `true`

### Page Rules (Alternativa)
1. **URL pattern:** `*maisaqu.com/api/*`
2. **Settings:**
   - Browser Cache TTL: `Respect Existing Headers`
   - Security Level: `Medium`
   - SSL: `Full`

## 📋 ORDEM DE PRIORIDADE

1. **🔥 CRÍTICO:** Executar `debug_500_error.sh`
2. **🔥 CRÍTICO:** Corrigir permissões (storage, bootstrap/cache)
3. **🔥 CRÍTICO:** Verificar logs do Laravel
4. **⚡ URGENTE:** Limpar caches do Laravel
5. **⚡ URGENTE:** Configurar CORS no Cloudflare
6. **📊 IMPORTANTE:** Habilitar módulos Apache
7. **📊 IMPORTANTE:** Verificar VirtualHost

## 🎯 RESULTADO ESPERADO

Após executar as correções:
- ✅ Erro 500 resolvido
- ✅ API respondendo corretamente
- ✅ Headers CORS presentes
- ✅ Requisições OPTIONS e POST funcionando

## 📞 SE PROBLEMA PERSISTIR

1. Verificar se o domínio está apontando corretamente
2. Verificar configuração SSL/TLS no Cloudflare
3. Considerar bypass temporário do Cloudflare
4. Contatar administrador do servidor
5. Verificar configuração do banco de dados

---
**Última atualização:** $(date)
**Status:** Erro 500 bloqueando CORS - Ação imediata necessária