# Correção CORS em Produção - Guia de Deploy

## Problema Identificado

O servidor de produção `https://api-clubeyellow.maisaqui.com.br` está retornando:
- **Status 500** no endpoint `/api/v1/login`
- **Headers CORS ausentes** (Access-Control-Allow-Origin)
- **Bloqueio de requisições cross-origin** do frontend

## Teste Realizado

✅ **Local**: Headers CORS funcionando corretamente (mesmo com erro 401)
❌ **Produção**: Headers CORS ausentes com erro 500

## Solução

### 1. Verificar Arquivo .htaccess

O arquivo `public/.htaccess` já está configurado corretamente com:

```apache
<IfModule mod_headers.c>
    # Definir origens permitidas específicas
    SetEnvIf Origin "^https?://(api-clubeyellow\.maisaqui\.com\.br|clubeyellow\.maisaqui\.com\.br|yellow-dev\.localhost:8000|localhost:8000|127\.0\.0\.1:8000)$" CORS_ALLOW_ORIGIN=$0

    # Headers CORS sempre enviados, mesmo em caso de erro
    Header always set Access-Control-Allow-Origin "%{CORS_ALLOW_ORIGIN}e" env=CORS_ALLOW_ORIGIN
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN, x-form-token"
    Header always set Access-Control-Allow-Credentials "true"
    Header always set Access-Control-Max-Age "3600"

    # Para origens não permitidas, definir origem como * sem credenciais
    Header always set Access-Control-Allow-Origin "*" "expr=!%{CORS_ALLOW_ORIGIN}"
    Header always unset Access-Control-Allow-Credentials "expr=!%{CORS_ALLOW_ORIGIN}"
    
    # Garantir que headers CORS sejam enviados mesmo em erros 500
    Header always set Access-Control-Allow-Origin "https://clubeyellow.maisaqui.com.br" "expr=%{REQUEST_URI} =~ m#^/api/#"
</IfModule>

# Handle preflight OPTIONS requests
RewriteCond %{REQUEST_METHOD} OPTIONS
RewriteRule ^(.*)$ $1 [R=200,L]
```

### 2. Passos para Deploy em Produção

#### Passo 1: Copiar arquivo .htaccess
```bash
# Fazer backup do .htaccess atual
cp public/.htaccess public/.htaccess.backup

# Copiar o novo .htaccess
cp public/.htaccess /caminho/para/producao/public/.htaccess
```

#### Passo 2: Verificar mod_headers no Apache
```bash
# Verificar se mod_headers está habilitado
apache2ctl -M | grep headers

# Se não estiver habilitado, habilitar:
a2enmod headers
systemctl reload apache2
```

#### Passo 3: Verificar configuração do Apache
Certificar-se de que o Apache permite override de headers:

```apache
# No arquivo de configuração do site (ex: /etc/apache2/sites-available/api-clubeyellow.conf)
<Directory /var/www/html/public>
    AllowOverride All
    Options -Indexes
    Require all granted
</Directory>
```

#### Passo 4: Testar a configuração
```bash
# Testar configuração do Apache
apache2ctl configtest

# Se OK, recarregar
systemctl reload apache2
```

### 3. Verificação Pós-Deploy

Executar o script de teste:
```powershell
./test_production_cors.ps1
```

**Resultado esperado:**
- ✅ Status OPTIONS: 200 ou 204
- ✅ Access-Control-Allow-Origin: https://clubeyellow.maisaqui.com.br
- ✅ Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
- ✅ Access-Control-Allow-Headers: Content-Type, Authorization, ...

### 4. Resolução do Erro 500

O erro 500 precisa ser investigado separadamente:

1. **Verificar logs do Apache:**
   ```bash
   tail -f /var/log/apache2/error.log
   ```

2. **Verificar logs do Laravel:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Possíveis causas:**
   - Configuração de banco de dados
   - Permissões de arquivo
   - Configuração de tenancy
   - Variáveis de ambiente (.env)

### 5. Checklist de Verificação

- [ ] Arquivo .htaccess copiado para produção
- [ ] mod_headers habilitado no Apache
- [ ] AllowOverride All configurado
- [ ] Apache recarregado
- [ ] Teste CORS executado com sucesso
- [ ] Erro 500 investigado e resolvido
- [ ] Frontend testado com sucesso

### 6. Contatos de Emergência

Em caso de problemas:
- Verificar logs em tempo real
- Restaurar backup do .htaccess se necessário
- Contatar administrador do servidor

---

**Última atualização:** $(Get-Date -Format "yyyy-MM-dd HH:mm:ss")
**Testado em:** Ambiente local (funcionando)
**Pendente:** Deploy em produção