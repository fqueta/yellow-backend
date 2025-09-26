# CORREÇÃO URGENTE - CORS em Produção

## ⚠️ PROBLEMA CONFIRMADO

O teste no servidor de produção confirmou:
- ✗ Erro 500 Internal Server Error
- ✗ Headers CORS completamente ausentes
- ✗ Requisições OPTIONS e POST falhando

## 🚨 AÇÃO IMEDIATA NECESSÁRIA

### 1. Verificar se o arquivo .htaccess existe no servidor
```bash
# No servidor de produção
ls -la /path/to/your/laravel/public/.htaccess
```

### 2. Se não existir, copiar o arquivo .htaccess
```bash
# Copiar do repositório local para o servidor
scp public/.htaccess user@server:/path/to/laravel/public/
```

### 3. Verificar permissões do arquivo
```bash
# Definir permissões corretas
chmod 644 /path/to/laravel/public/.htaccess
chown www-data:www-data /path/to/laravel/public/.htaccess
```

### 4. Verificar se mod_headers está habilitado
```bash
# Ubuntu/Debian
sudo a2enmod headers
sudo systemctl reload apache2

# CentOS/RHEL
sudo systemctl reload httpd
```

### 5. Verificar configuração do Apache
Verificar se o VirtualHost permite .htaccess:
```apache
<Directory "/path/to/laravel/public">
    AllowOverride All
    Require all granted
</Directory>
```

### 6. Verificar logs do Apache para o erro 500
```bash
# Verificar logs de erro
sudo tail -f /var/log/apache2/error.log
# ou
sudo tail -f /var/log/httpd/error_log
```

### 7. Verificar logs do Laravel
```bash
# Verificar logs do Laravel
tail -f /path/to/laravel/storage/logs/laravel.log
```

## 📋 CHECKLIST DE VERIFICAÇÃO

- [ ] Arquivo .htaccess existe em `/public/`
- [ ] Permissões do .htaccess estão corretas (644)
- [ ] mod_headers está habilitado no Apache
- [ ] AllowOverride All está configurado
- [ ] Apache foi recarregado após mudanças
- [ ] Logs verificados para identificar causa do erro 500

## 🔧 CONTEÚDO DO .HTACCESS CORRETO

O arquivo deve conter:
```apache
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
```

## 🆘 SE O PROBLEMA PERSISTIR

1. **Verificar se é problema do Cloudflare:**
   - O servidor está atrás do Cloudflare
   - Verificar configurações de proxy no Cloudflare

2. **Verificar configuração do Laravel:**
   - Verificar arquivo `config/cors.php`
   - Verificar middleware CORS

3. **Contato de emergência:**
   - Administrador do servidor: webmaster@api-clubeyellow.maisaqui.com.br

## ⚡ TESTE APÓS CORREÇÃO

Após aplicar as correções, executar novamente:
```bash
/tmp/test_production_cors.sh
```

Os headers CORS devem aparecer nas respostas, mesmo com erro 500.