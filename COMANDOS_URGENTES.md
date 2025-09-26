# 🚨 COMANDOS URGENTES - CORREÇÃO CORS

## 📋 SITUAÇÃO ATUAL
- ✅ .htaccess existe com configurações CORS
- ❌ Servidor web não detectado corretamente
- ❌ mod_headers/mod_rewrite não habilitados
- ❌ Cloudflare interceptando requisições
- ❌ Erro 500 sem headers CORS

## ⚡ AÇÕES IMEDIATAS

### 1. Executar Novo Script de Diagnóstico
```bash
# Copiar e executar o novo script
chmod +x fix_cors_cloudflare.sh
./fix_cors_cloudflare.sh
```

### 2. Identificar Servidor Web Real
```bash
# Verificar processos rodando
ps aux | grep -E 'apache|nginx|httpd|litespeed'

# Verificar portas
netstat -tlnp | grep :80
netstat -tlnp | grep :443

# Verificar qual servidor está servindo o site
curl -I http://localhost/yellow-backend/public/
```

### 3. Habilitar Módulos Apache (se for Apache)
```bash
# Tentar diferentes comandos
sudo a2enmod rewrite headers
# OU
sudo /usr/sbin/a2enmod rewrite headers
# OU
sudo httpd -M | grep -E 'rewrite|headers'

# Recarregar Apache
sudo systemctl reload apache2
# OU
sudo systemctl reload httpd
# OU
sudo service apache2 reload
```

### 4. Verificar Configuração VirtualHost
```bash
# Localizar arquivo de configuração
find /etc -name "*.conf" | grep -E 'apache|httpd' | grep -v ssl

# Verificar configuração atual
grep -r "DocumentRoot.*yellow-backend" /etc/apache2/ 2>/dev/null
grep -r "DocumentRoot.*yellow-backend" /etc/httpd/ 2>/dev/null

# Verificar AllowOverride
grep -r "AllowOverride" /etc/apache2/ 2>/dev/null
grep -r "AllowOverride" /etc/httpd/ 2>/dev/null
```

### 5. Configuração Manual VirtualHost
```bash
# Editar configuração do site (encontrar o arquivo correto primeiro)
sudo nano /etc/apache2/sites-available/000-default.conf
# OU
sudo nano /etc/httpd/conf.d/vhost.conf
```

**Adicionar dentro do VirtualHost:**
```apache
<Directory "/home/maisaqu/public_html/yellow-backend/public">
    AllowOverride All
    Require all granted
</Directory>

# Headers CORS globais (backup do .htaccess)
Header always set Access-Control-Allow-Origin "https://yellow-dev.localhost:3000"
Header always set Access-Control-Allow-Credentials "true"
Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"
```

### 6. Se for Nginx
```bash
# Verificar configuração
nginx -t
cat /etc/nginx/sites-available/default

# Editar configuração
sudo nano /etc/nginx/sites-available/default
```

**Adicionar no bloco server:**
```nginx
# Headers CORS sempre
add_header Access-Control-Allow-Origin "https://yellow-dev.localhost:3000" always;
add_header Access-Control-Allow-Credentials "true" always;
add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS" always;
add_header Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With" always;

# Responder OPTIONS
if ($request_method = 'OPTIONS') {
    return 200;
}
```

### 7. Testar Localmente (Bypass Cloudflare)
```bash
# Testar direto no servidor
curl -i -X OPTIONS \
  -H "Origin: https://yellow-dev.localhost:3000" \
  "http://localhost/yellow-backend/public/api/v1/login"

# Testar POST local
curl -i -X POST \
  -H "Content-Type: application/json" \
  -H "Origin: https://yellow-dev.localhost:3000" \
  -d '{"email":"test@test.com","password":"123456"}' \
  "http://localhost/yellow-backend/public/api/v1/login"
```

### 8. Verificar Logs de Erro
```bash
# Laravel
tail -f /home/maisaqu/public_html/yellow-backend/storage/logs/laravel.log

# Apache
tail -f /var/log/apache2/error.log
tail -f /var/log/httpd/error_log

# Nginx
tail -f /var/log/nginx/error.log

# PHP
tail -f /var/log/php-fpm/www-error.log
```

## 🔧 CONFIGURAÇÃO CLOUDFLARE

### Opção 1: Page Rules
1. Acessar painel Cloudflare
2. Ir para "Rules" > "Page Rules"
3. Criar regra para `api-clubeyellow.maisaqui.com.br/api/*`
4. Adicionar configurações:
   - Cache Level: Bypass
   - Security Level: Medium
   - Browser Integrity Check: Off

### Opção 2: Transform Rules (Recomendado)
1. Ir para "Rules" > "Transform Rules"
2. Criar "HTTP Response Header Modification"
3. Configurar:
   - **If**: `hostname equals api-clubeyellow.maisaqui.com.br`
   - **Then**: Add headers:
     - `Access-Control-Allow-Origin: https://yellow-dev.localhost:3000`
     - `Access-Control-Allow-Credentials: true`
     - `Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS`
     - `Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With`

### Opção 3: Worker (Avançado)
```javascript
addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request))
})

async function handleRequest(request) {
  const response = await fetch(request)
  const newResponse = new Response(response.body, response)
  
  // Adicionar headers CORS sempre
  newResponse.headers.set('Access-Control-Allow-Origin', 'https://yellow-dev.localhost:3000')
  newResponse.headers.set('Access-Control-Allow-Credentials', 'true')
  newResponse.headers.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
  newResponse.headers.set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
  
  return newResponse
}
```

## 🎯 TESTE FINAL

Após cada mudança, testar:
```bash
# Teste completo
./test_production_cors.sh

# Ou teste manual
curl -i -X OPTIONS \
  -H "Origin: https://yellow-dev.localhost:3000" \
  "https://api-clubeyellow.maisaqui.com.br/api/v1/login"
```

## 📞 ORDEM DE PRIORIDADE

1. **URGENTE**: Executar `fix_cors_cloudflare.sh`
2. **CRÍTICO**: Habilitar mod_headers no Apache
3. **IMPORTANTE**: Configurar AllowOverride All
4. **NECESSÁRIO**: Configurar Cloudflare Transform Rules
5. **VERIFICAÇÃO**: Testar endpoints

## ✅ RESULTADO ESPERADO

Após correção, deve retornar:
```
HTTP/2 200
access-control-allow-origin: https://yellow-dev.localhost:3000
access-control-allow-credentials: true
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS
access-control-allow-headers: Content-Type, Authorization, X-Requested-With
```

**IMPORTANTE**: Mesmo com erro 500, os headers CORS devem estar presentes!