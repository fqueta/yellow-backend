# Teste de CORS no Servidor de Produção Linux

## Problema Identificado
O script PowerShell `test_production_cors.ps1` não pode ser executado no servidor Linux. Foi criado um script bash equivalente.

## Solução

### 1. Copiar o script para o servidor
```bash
# No servidor de produção
cp test_production_cors.sh /tmp/
chmod +x /tmp/test_production_cors.sh
```

### 2. Executar o teste
```bash
# Executar o script de teste
/tmp/test_production_cors.sh
```

### 3. Alternativa: Teste manual com curl
Se preferir testar manualmente:

```bash
# Teste OPTIONS (preflight)
curl -i -X OPTIONS \
  -H "Origin: https://yellow-dev.localhost:3000" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type" \
  "https://api-clubeyellow.maisaqui.com.br/api/v1/login"

# Teste POST
curl -i -X POST \
  -H "Content-Type: application/json" \
  -H "Origin: https://yellow-dev.localhost:3000" \
  -d '{"email":"test@example.com","password":"password"}' \
  "https://api-clubeyellow.maisaqui.com.br/api/v1/login"
```

## O que verificar

### Headers CORS esperados:
- `Access-Control-Allow-Origin: https://yellow-dev.localhost:3000`
- `Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS`
- `Access-Control-Allow-Headers: Content-Type, Authorization`
- `Access-Control-Allow-Credentials: true`

### Se os headers estiverem ausentes:
1. Verificar se o arquivo `.htaccess` foi copiado corretamente
2. Verificar se `mod_headers` está habilitado
3. Verificar se `AllowOverride All` está configurado
4. Verificar logs do Apache para erros

## Próximos passos
Após executar o teste, se os headers CORS ainda estiverem ausentes, será necessário:
1. Aplicar a configuração `.htaccess` no servidor
2. Verificar configuração do Apache
3. Investigar o erro 500 nos logs do servidor