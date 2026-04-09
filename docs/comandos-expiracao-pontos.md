# Comandos de Expiração de Pontos

Este documento descreve os comandos Artisan disponíveis para gerenciar a expiração de pontos no sistema.

## Requisitos

- Configuração: `pontos_dias_expiracao` (opção do sistema que define quantos dias após o crédito os pontos expiram)

---

## 1. `points:expire`

Expira pontos de crédito vencidos em lote.

### Descrição

Busca todos os pontos de crédito ativos com `data_expiracao` menor que hoje e marca seu status como `expirado`.

### Uso

```bash
php artisan points:expire
```

### Comportamento

- Itera sobre todos os tenants (multi-tenancy)
- Para cada tenant, busca pontos que:
  - São do tipo `credito`
  - Estão com status `ativo`
  - Estão ativos (`ativo = 's'`)
  - Não estão excluídos (`excluido = 'n'`)
  - Não estão deletados (`deletado = 'n'`)
  - Têm `data_expiracao` menor que hoje
- Atualiza o status para `expirado`

### Saída Exemplo

```
Iniciando expiração de pontos...
  Tenant [tenant_1]: 15 pontos expirados
  Tenant [tenant_2]: 8 pontos expirados
Concluído! Total de pontos expirados: 23
```

### Agendamento

Este comando está configurado internamente para rodar diariamente às 00:00, dentro da rotina de comandos do Laravel:

```php
// routes/console.php
Schedule::command('points:expire')->daily();
```

> **IMPORTANTE**: Para que o agendamento funcione automaticamente na produção, você deve configurar o CRON no seu servidor (cPanel, Plesk, ou via terminal Linux).

#### Configuração do CRON no Servidor

Adicione a seguinte linha no seu gerenciador de tarefas CRON do servidor para rodar a cada minuto (o Laravel cuidará de executar o schedule apenas na hora certa):

```bash
* * * * * cd /home/maisaqu/public_html/point_store/yellow-backend && php artisan schedule:run >> /dev/null 2>&1
```

*(Lembre-se de verificar no servidor se o caminho do PHP, como `/opt/cpanel/ea-php83/root/usr/bin/php` precisa ser usado no lugar de apenas `php` dependendo da sua hospedagem)*

---

## 2. `points:retroactive-expiration`

Aplica a regra de expiração retroativamente a pontos antigos que não possuem data de expiração.

### Descrição

Busca todos os pontos de crédito ativos que não possuem `data_expiracao` preenchida e define a data automaticamente com base na configuração `pontos_dias_expiracao`.

### Uso

```bash
php artisan points:retroactive-expiration
```

### Comportamento

- Verifica se a opção `pontos_dias_expiracao` está configurada
- Itera sobre todos os tenants (multi-tenancy)
- Para cada tenant, busca pontos que:
  - São do tipo `credito`
  - Estão com status `ativo`
  - Estão ativos (`ativo = 's'`)
  - Não estão excluídos (`excluido = 'n'`)
  - Não estão deletados (`deletado = 'n'`)
  - NÃO têm `data_expiracao` preenchida
- Calcula a data de expiração: data do ponto + dias configurados
- Atualiza o campo `data_expiracao`

### Saída Exemplo

```
Iniciando atualização retroativa de expiração de pontos...
  Tenant [tenant_1]: 42 pontos atualizados retroativamente.
  Tenant [tenant_2]: 10 pontos atualizados retroativamente.
Concluído! Total de pontos atualizados retroativamente: 52
```

### Configuração

A opção `pontos_dias_expiracao` deve ser configurada nas opções do sistema. Exemplo de valores:
- `30` = 30 dias
- `365` = 1 ano

---

## Notificações

Ambos os comandos enviam notificações por email aos administradores do sistema após a execução.

### Requisitos

- Usuários com `permission_id = 1` recebem a notificação
- O sistema deve ter o canal Brevo configurado corretamente

### Canal de Envio

As notificações são enviadas via **Brevo** (canal transactional email).

### Notificação Enviada

- **Assunto**: "Expiração de Pontos - Relatório"
- **Conteúdo**: Número de pontos expirados/atualizados, data da execução e identificação do tenant

---

## Fluxo Recomendado

1. **Primeira vez**: Rode `points:retroactive-expiration` para aplicar a data de expiração aos pontos existentes
2. **Rotina diária**: O comando `points:expire` roda automaticamente todo dia às 00:00
3. **Execução manual**: Rode `points:expire` manualmente quando necessário

---

## Troubleshooting

### "Regra de expiração não configurada"

O comando `points:retroactive-expiration` requer que a opção `pontos_dias_expiracao` esteja configurada no sistema. Configure-a primeiro.

### "Nenhum tenant encontrado"

O sistema não encontrou tenants para processar. Verifique se existem tenants cadastrados.

### Falha ao enviar notificação

Verifique se:
- O canal Brevo está configurado corretamente
- Os usuários com `permission_id = 1` existem e têm email válido
- O modelo User está disponível no contexto atual
