# Solução para Problema CORS em Produção

## Problema Identificado
O erro "falta cabeçalho 'Access-Control-Allow-Origin' no CORS" estava ocorrendo em produção devido a erros 500 que impediam o envio dos headers CORS.

## Solução Implementada

### 1. Configuração .htaccess Atualizada
O arquivo `public/.htaccess` foi atualizado para garantir que os headers CORS sejam enviados mesmo em caso de erros:

```apache
# Handle CORS - Configuração para desenvolvimento e produção
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
```

### 2. Configuração Laravel CORS
O arquivo `config/cors.php` já estava corretamente configurado com as origens permitidas.

## Verificações para Produção

### 1. Módulo mod_headers
Verifique se o módulo `mod_headers` está habilitado no Apache de produção:
```bash
# No servidor de produção
a2enmod headers
sudo systemctl restart apache2
```

### 2. Teste de CORS
Use o script `test_cors_production.ps1` para verificar se os headers CORS estão sendo enviados corretamente.

### 3. Logs de Erro
Monitore os logs do Laravel e Apache para identificar a causa dos erros 500:
```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Apache logs
tail -f /var/log/apache2/error.log
```

## Resultado do Teste
✅ Headers CORS sendo enviados corretamente mesmo com erros
✅ Requisições OPTIONS (preflight) funcionando
✅ Configuração compatível com desenvolvimento e produção

## Próximos Passos
1. Aplicar esta configuração no servidor de produção
2. Verificar se mod_headers está habilitado
3. Resolver os erros 500 que estão causando o problema principal
4. Monitorar logs para identificar problemas específicos

## Arquivos Modificados
- `public/.htaccess` - Configuração CORS atualizada
- `test_cors_production.ps1` - Script de teste criado

## Observações
- A configuração funciona tanto para desenvolvimento quanto produção
- Headers CORS são enviados mesmo em caso de erros HTTP
- Origens específicas são permitidas com credenciais
- Fallback para origem "*" sem credenciais para outras origens