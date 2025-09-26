# CONFIGURAÇÃO CLOUDFLARE TRANSFORM RULES - CORS

## 🎯 OBJETIVO
Configurar headers CORS diretamente no Cloudflare para resolver o problema de CORS mesmo com erro 500 no servidor.

## 🚨 SITUAÇÃO ATUAL
- ❌ Servidor retornando erro 500
- ❌ Headers CORS ausentes
- ✅ Cloudflare interceptando requisições
- ✅ Possibilidade de adicionar headers via Transform Rules

## 📋 PASSO A PASSO - TRANSFORM RULES

### 1. ACESSAR PAINEL CLOUDFLARE
1. Acesse: https://dash.cloudflare.com
2. Selecione o domínio: **maisaqu.com**
3. No menu lateral, clique em: **Rules**
4. Selecione: **Transform Rules**
5. Clique na aba: **Modify Response Header**

### 2. CRIAR REGRA CORS PARA API

#### Configuração da Regra:
- **Rule name:** `CORS Headers for API`
- **Description:** `Add CORS headers to all API endpoints`

#### When incoming requests match:
```
Field: URI Path
Operator: starts with
Value: /api/
```

#### Then modify response header:
**Adicionar os seguintes headers:**

1. **Access-Control-Allow-Origin**
   - Action: `Set static`
   - Header name: `Access-Control-Allow-Origin`
   - Value: `*`

2. **Access-Control-Allow-Methods**
   - Action: `Set static`
   - Header name: `Access-Control-Allow-Methods`
   - Value: `GET, POST, PUT, DELETE, OPTIONS, PATCH`

3. **Access-Control-Allow-Headers**
   - Action: `Set static`
   - Header name: `Access-Control-Allow-Headers`
   - Value: `Content-Type, Authorization, X-Requested-With, Accept, Origin`

4. **Access-Control-Allow-Credentials**
   - Action: `Set static`
   - Header name: `Access-Control-Allow-Credentials`
   - Value: `true`

5. **Access-Control-Max-Age**
   - Action: `Set static`
   - Header name: `Access-Control-Max-Age`
   - Value: `86400`

### 3. CRIAR REGRA ESPECÍFICA PARA OPTIONS

#### Segunda Regra (Opcional, mas recomendada):
- **Rule name:** `CORS Preflight OPTIONS`
- **Description:** `Handle OPTIONS preflight requests`

#### When incoming requests match:
```
Field: Request Method
Operator: equals
Value: OPTIONS

AND

Field: URI Path
Operator: starts with
Value: /api/
```

#### Then modify response header:
**Adicionar os mesmos headers da regra anterior**

### 4. CONFIGURAÇÃO AVANÇADA (SE NECESSÁRIO)

#### Para domínios específicos (mais seguro):
Substituir `*` por domínios específicos:
```
Access-Control-Allow-Origin: https://yellow-dev.localhost, https://localhost:3000
```

#### Para múltiplos domínios:
Criar regras separadas para cada domínio ou usar expressão regular.

## 🔧 CONFIGURAÇÕES ADICIONAIS CLOUDFLARE

### 1. SSL/TLS Settings
- **SSL/TLS encryption mode:** `Full (strict)` ou `Full`
- **Always Use HTTPS:** `On`
- **Minimum TLS Version:** `1.2`

### 2. Security Settings
- **Security Level:** `Medium` ou `Low` (temporariamente)
- **Browser Integrity Check:** `Off` (temporariamente)
- **Challenge Passage:** `30 minutes`

### 3. Speed Settings
- **Auto Minify:** Desabilitar para JavaScript (pode causar problemas)
- **Rocket Loader:** `Off` (pode interferir com CORS)

### 4. Caching Settings
- **Caching Level:** `Standard`
- **Browser Cache TTL:** `Respect Existing Headers`

## 📊 PAGE RULES (ALTERNATIVA)

Se Transform Rules não estiverem disponíveis:

### Criar Page Rule:
1. **URL pattern:** `*maisaqu.com/api/*`
2. **Settings:**
   - **Security Level:** `Medium`
   - **Cache Level:** `Bypass`
   - **Disable Security:** `On` (temporariamente)
   - **SSL:** `Full`

## 🧪 TESTE APÓS CONFIGURAÇÃO

### Comando para testar:
```bash
# Teste OPTIONS (preflight)
curl -X OPTIONS \
  -H "Origin: https://yellow-dev.localhost" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type, Authorization" \
  -v "https://maisaqu.com/api/v1/login"

# Teste POST
curl -X POST \
  -H "Origin: https://yellow-dev.localhost" \
  -H "Content-Type: application/json" \
  -v "https://maisaqu.com/api/v1/login"
```

### Resultado esperado:
```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin
Access-Control-Allow-Credentials: true
Access-Control-Max-Age: 86400
```

## 🚀 CLOUDFLARE WORKERS (SOLUÇÃO AVANÇADA)

Se Transform Rules não resolverem:

### Worker Script:
```javascript
addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request))
})

async function handleRequest(request) {
  // Handle preflight OPTIONS request
  if (request.method === 'OPTIONS') {
    return new Response(null, {
      status: 200,
      headers: {
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS, PATCH',
        'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
        'Access-Control-Allow-Credentials': 'true',
        'Access-Control-Max-Age': '86400'
      }
    })
  }

  // Forward other requests and add CORS headers to response
  const response = await fetch(request)
  const newResponse = new Response(response.body, response)
  
  newResponse.headers.set('Access-Control-Allow-Origin', '*')
  newResponse.headers.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH')
  newResponse.headers.set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin')
  newResponse.headers.set('Access-Control-Allow-Credentials', 'true')
  
  return newResponse
}
```

### Configurar Worker:
1. **Workers & Pages** > **Create application**
2. **Create Worker**
3. Cole o código acima
4. **Deploy**
5. **Add route:** `maisaqu.com/api/*`

## ⚡ VERIFICAÇÃO RÁPIDA

### Após configurar Transform Rules:
```bash
# Executar script de teste
./test_production_cors.sh
```

### Resultado esperado:
- ✅ Headers CORS presentes
- ✅ OPTIONS request funcionando
- ⚠️ POST pode ainda retornar 500, mas com headers CORS

## 🎯 PRÓXIMOS PASSOS

1. **Configurar Transform Rules** (prioridade máxima)
2. **Testar CORS** com `test_production_cors.sh`
3. **Corrigir erro 500** no servidor (paralelo)
4. **Verificar logs** do Laravel
5. **Otimizar configurações** do Cloudflare

---
**Status:** Transform Rules = Solução imediata para CORS
**Próximo:** Corrigir erro 500 no Laravel para funcionalidade completa