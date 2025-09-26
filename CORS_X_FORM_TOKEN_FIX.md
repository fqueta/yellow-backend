# CORREÇÃO CORS - HEADER X-FORM-TOKEN

## 🚨 PROBLEMA IDENTIFICADO

**Erro específico:**
```
Requisição cross-origin bloqueada: A diretiva Same Origin (mesma origem) não permite a leitura do recurso remoto em `https://api-clubeyellow.maisaqui.com.br/api/v1/clients/active` (motivo: header 'x-form-token' não permitido, de acordo com o header 'Access-Control-Allow-Headers' da resposta de comprovação (preflight) do CORS).
```

**Causa raiz:**
- O header `x-form-token` não estava incluído na lista de headers permitidos
- CORS preflight request falhando devido a header não autorizado
- NetworkError resultante do bloqueio CORS

## ✅ SOLUÇÃO IMPLEMENTADA

### 1. Atualização do .htaccess

**Arquivo:** `public/.htaccess`

**Antes:**
```apache
Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN"
```

**Depois:**
```apache
Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN, x-form-token"
```

### 2. Atualização Cloudflare Transform Rules

**Headers permitidos atualizados:**
```
Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN, x-form-token
```

## 🔧 CONFIGURAÇÃO COMPLETA CORS

### Headers CORS Necessários:

1. **Access-Control-Allow-Origin:** `https://clubeyellow.maisaqui.com.br`
2. **Access-Control-Allow-Methods:** `GET, POST, PUT, DELETE, OPTIONS`
3. **Access-Control-Allow-Headers:** `Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN, x-form-token`
4. **Access-Control-Allow-Credentials:** `true`
5. **Access-Control-Max-Age:** `3600`

### Configuração Apache (.htaccess):
```apache
<IfModule mod_headers.c>
    Header always set Access-Control-Allow-Origin "https://clubeyellow.maisaqui.com.br"
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN, x-form-token"
    Header always set Access-Control-Allow-Credentials "true"
    Header always set Access-Control-Max-Age "3600"
</IfModule>
```

### Configuração Cloudflare Transform Rules:
```
Field: URI Path
Operator: starts with
Value: /api/

Headers to add:
- Access-Control-Allow-Origin: https://clubeyellow.maisaqui.com.br
- Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
- Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN, x-form-token
- Access-Control-Allow-Credentials: true
- Access-Control-Max-Age: 3600
```

## 🧪 TESTE DA CORREÇÃO

### Comando de teste:
```bash
# Teste preflight com x-form-token
curl -X OPTIONS \
  -H "Origin: https://clubeyellow.maisaqui.com.br" \
  -H "Access-Control-Request-Method: GET" \
  -H "Access-Control-Request-Headers: Content-Type, Authorization, x-form-token" \
  -v "https://api-clubeyellow.maisaqui.com.br/api/v1/clients/active"
```

### Resultado esperado:
```
Access-Control-Allow-Origin: https://clubeyellow.maisaqui.com.br
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN, x-form-token
Access-Control-Allow-Credentials: true
```

## 🚀 IMPLEMENTAÇÃO URGENTE

### Passos para aplicar a correção:

1. **Atualizar .htaccess no servidor:**
   ```bash
   # Fazer backup
   cp public/.htaccess public/.htaccess.backup
   
   # Aplicar nova configuração
   # (arquivo já atualizado localmente)
   ```

2. **Configurar Cloudflare Transform Rules:**
   - Acessar painel Cloudflare
   - Rules > Transform Rules > Modify Response Header
   - Atualizar regra existente ou criar nova
   - Incluir `x-form-token` nos headers permitidos

3. **Testar a correção:**
   ```bash
   ./test_cors_clubeyellow.sh
   ```

4. **Verificar logs se problema persistir:**
   ```bash
   tail -f storage/logs/laravel.log
   tail -f /var/log/apache2/error.log
   ```

## 📋 HEADERS PERSONALIZADOS COMUNS

Para evitar problemas futuros, considere incluir estes headers comuns:

```
Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN, x-form-token, X-API-Key, X-Client-Version, X-Device-ID, X-Session-Token
```

## ⚠️ CONSIDERAÇÕES DE SEGURANÇA

1. **Origem específica:** Mantida `https://clubeyellow.maisaqui.com.br` (não usar `*`)
2. **Credenciais habilitadas:** Necessário para autenticação
3. **Headers limitados:** Apenas headers necessários incluídos
4. **Cache CORS:** 3600 segundos para performance

## 🔍 DIAGNÓSTICO ADICIONAL

Se o problema persistir após a correção:

1. **Verificar se mod_headers está habilitado:**
   ```bash
   apache2ctl -M | grep headers
   ```

2. **Verificar AllowOverride:**
   ```bash
   grep -r "AllowOverride" /etc/apache2/sites-available/
   ```

3. **Testar configuração Apache:**
   ```bash
   apache2ctl configtest
   ```

4. **Recarregar Apache:**
   ```bash
   systemctl reload apache2
   ```

## 📊 STATUS DA CORREÇÃO

- ✅ Header `x-form-token` adicionado ao .htaccess
- ✅ Documentação Cloudflare atualizada
- ✅ Configuração testável disponível
- 🔄 Aguardando aplicação no servidor de produção

---
**Próximo passo:** Aplicar as configurações no servidor e testar o endpoint `https://api-clubeyellow.maisaqui.com.br/api/v1/clients/active`