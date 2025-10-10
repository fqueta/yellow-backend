# Integração com API Brevo - Sistema Yellow

## 📋 Visão Geral

Esta documentação descreve a integração completa da API do Brevo no sistema Yellow para resolver os problemas de SSL/SMTP e melhorar a confiabilidade do envio de emails.

## 🚀 Arquivos Criados/Modificados

### Novos Arquivos

1. **`app/Services/BrevoEmailService.php`** - Serviço principal para integração com API Brevo
2. **`app/Console/Commands/TestBrevoEmail.php`** - Comando Artisan para testes
3. **`app/Http/Controllers/api/BrevoController.php`** - Controller para gerenciar via API
4. **`app/Http/Middleware/CheckBrevoConfiguration.php`** - Middleware de verificação
5. **`.env.brevo.example`** - Exemplo de configuração
6. **`BREVO_INTEGRATION.md`** - Esta documentação

### Arquivos Modificados

1. **`config/services.php`** - Adicionada configuração Brevo
2. **`app/Jobs/SendRedemptionNotification.php`** - Atualizado para usar Brevo
3. **`routes/tenant.php`** - Adicionadas rotas da API Brevo

## ⚙️ Configuração

### 1. Obter Chave API do Brevo

1. Acesse [https://app.brevo.com/settings/keys/api](https://app.brevo.com/settings/keys/api)
2. Crie uma nova chave API
3. Copie a chave gerada

### 2. Configurar Variáveis de Ambiente

Adicione no seu arquivo `.env`:

```env
# Configuração Brevo
BREVO_API_KEY=xkeysib-sua_chave_api_aqui
BREVO_API_URL=https://api.brevo.com/v3

# Configuração de email (mantém compatibilidade)
MAIL_FROM_ADDRESS=noreply@seudominio.com
MAIL_FROM_NAME="Sistema Yellow"
```

### 3. Limpar Cache

```bash
php artisan config:clear
php artisan cache:clear
```

## 🧪 Testes

### 1. Teste via Comando Artisan

```bash
# Teste básico
php artisan brevo:test-email seu@email.com

# Teste com verificação de API
php artisan brevo:test-email seu@email.com --check-api

# Teste com nome personalizado
php artisan brevo:test-email seu@email.com --name="João Silva"
```

### 2. Teste via API REST

#### Verificar Configuração
```bash
GET /api/v1/brevo/configuration
```

#### Verificar Status da API
```bash
GET /api/v1/brevo/api-status
```

#### Enviar Email de Teste
```bash
POST /api/v1/brevo/test-email
Content-Type: application/json

{
    "email": "seu@email.com",
    "name": "Seu Nome",
    "subject": "Teste Personalizado"
}
```

#### Simular Notificação de Resgate
```bash
POST /api/v1/brevo/simulate-redemption
Content-Type: application/json

{
    "user_email": "cliente@email.com",
    "user_name": "Cliente Teste",
    "product_name": "Produto Exemplo",
    "quantity": 2,
    "points_used": 100
}
```

## 📧 Funcionalidades

### 1. Envio de Notificações de Resgate

O sistema agora envia automaticamente:

- **Email para o usuário**: Confirmação de resgate realizado
- **Email para administradores**: Notificação de novo resgate

### 2. Templates de Email

Todos os emails incluem:

- Design responsivo e moderno
- Informações detalhadas do resgate
- Branding do sistema
- Formatação HTML profissional

### 3. Logs Detalhados

Todos os envios são registrados em `storage/logs/laravel.log` com:

- Status de envio
- Message ID do Brevo
- Detalhes do destinatário
- Informações de erro (se houver)

## 🔧 Uso Programático

### Exemplo Básico

```php
use App\Services\BrevoEmailService;

$brevoService = new BrevoEmailService();

// Envio simples
$result = $brevoService->sendTransactionalEmail(
    [['email' => 'destino@email.com', 'name' => 'Nome']],
    'Assunto do Email',
    '<h1>Conteúdo HTML</h1>'
);

if ($result['success']) {
    echo "Email enviado! ID: " . $result['message_id'];
}
```

### Exemplo de Notificação de Resgate

```php
$brevoService = new BrevoEmailService();

// Enviar para usuário
$result = $brevoService->sendRedemptionSuccessNotification(
    $user,
    $product,
    $redemption,
    $quantity,
    $pointsUsed
);

// Enviar para administradores
$admins = User::where('role', 'admin')->get();
$adminResult = $brevoService->sendAdminRedemptionNotification(
    $user,
    $product,
    $redemption,
    $quantity,
    $pointsUsed,
    $admins->toArray()
);
```

## 🛠️ Integração no ProductController

O arquivo `ProductController.php` na linha 718 já está configurado para usar o novo sistema:

```php
// Linha 718 - ProductController.php
\App\Jobs\SendRedemptionNotification::dispatch(
    $user,
    $product,
    $redemption,
    $quantity,
    $pointsUsed
);
```

O job `SendRedemptionNotification` foi atualizado para usar a API Brevo automaticamente.

## 📊 Monitoramento

### Verificar Status da Configuração

```php
use App\Http\Middleware\CheckBrevoConfiguration;

$status = CheckBrevoConfiguration::getBrevoConfigurationStatus();

if ($status['configuration_complete']) {
    echo "✅ Brevo configurado corretamente";
} else {
    echo "❌ Problemas na configuração";
}
```

### Verificar Logs

```bash
# Ver logs em tempo real
tail -f storage/logs/laravel.log | grep -i brevo

# Buscar logs específicos
grep "Brevo" storage/logs/laravel.log
```

## 🚨 Solução de Problemas

### Erro: "Chave API não configurada"

1. Verifique se `BREVO_API_KEY` está no `.env`
2. Execute `php artisan config:clear`
3. Teste com `php artisan brevo:test-email --check-api`

### Erro: "API inacessível"

1. Verifique sua conexão com internet
2. Confirme se a chave API está correta
3. Verifique se não há firewall bloqueando

### Emails não chegam

1. Verifique spam/lixo eletrônico
2. Confirme se o domínio está verificado no Brevo
3. Verifique logs para erros específicos

## 🎯 Vantagens da Integração

✅ **Confiabilidade**: API REST mais estável que SMTP
✅ **Sem SSL**: Elimina problemas de certificados
✅ **Rastreamento**: Melhor controle de entrega
✅ **Escalabilidade**: Suporta alto volume de emails
✅ **Logs**: Monitoramento detalhado
✅ **Flexibilidade**: Fácil personalização de templates

## 📞 Suporte

Para dúvidas ou problemas:

1. Verifique os logs em `storage/logs/laravel.log`
2. Execute os comandos de teste
3. Consulte a documentação oficial do Brevo
4. Verifique as configurações no painel do Brevo

---

**Integração concluída com sucesso! 🎉**

O sistema agora usa a API Brevo para todos os envios de email, resolvendo os problemas de SSL/SMTP anteriores.