# 🚀 Configuração Rápida - API Brevo

## ⚡ Passos para Ativar

### 1. Obter Chave API

1. Acesse: https://app.brevo.com/settings/keys/api
2. Clique em "Create a new API key"
3. Dê um nome (ex: "Sistema Yellow")
4. Copie a chave gerada

### 2. Configurar .env

Adicione estas linhas no seu arquivo `.env`:

```env
# API Brevo
BREVO_API_KEY=xkeysib-sua_chave_aqui
BREVO_API_URL=https://api.brevo.com/v3

# Email padrão (se ainda não tiver)
MAIL_FROM_ADDRESS=noreply@seudominio.com
MAIL_FROM_NAME="Sistema Yellow"
```

### 3. Aplicar Configurações

```bash
php artisan config:clear
php artisan cache:clear
```

### 4. Testar

```bash
# Teste básico
php artisan brevo:test-email seu@email.com

# Teste completo com verificação
php artisan brevo:test-email seu@email.com --check-api
```

## ✅ Verificação de Funcionamento

Se tudo estiver correto, você verá:

```
✅ API Brevo está funcionando corretamente!
✅ Email enviado com sucesso!
Message ID: <id_da_mensagem>
🎉 Teste concluído com sucesso!
```

## 🎯 Resultado

- ✅ Emails de resgate funcionando via API Brevo
- ✅ Sem problemas de SSL/certificados
- ✅ Logs detalhados em `storage/logs/laravel.log`
- ✅ Interface de teste via API REST

## 🔗 Endpoints Disponíveis

- `GET /api/v1/brevo/configuration` - Status da configuração
- `GET /api/v1/brevo/api-status` - Status da API
- `POST /api/v1/brevo/test-email` - Enviar email de teste
- `POST /api/v1/brevo/simulate-redemption` - Simular notificação

---

**Pronto! Sua integração Brevo está ativa! 🎉**