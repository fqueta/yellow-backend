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

> **IMPORTANTE**: Para que o agendamento funcione automaticamente na produção, você deve configurar o CRON no seu servidor.

#### Configuração no CWP (Control Web Panel) do Usuário

No painel de hospedagem (Crontab for user), preencha o formulário da seguinte maneira:

- **Command:**
  ```bash
  /usr/local/bin/php /home/maisaqu/public_html/point_store/yellow-backend/artisan schedule:run >> /dev/null 2>&1
  ```
- **Description:** Executar rotinas agendadas do sistema e expiração de pontos
- **When runs? (Quando rodar?):**
  - Mude a opção em "Simple schedule" para rodar a cada minuto: **`Every minute [* * * * *]`**.
  - *(Se a opção não existir na lista rápida, clique em "Show advanced options" e coloque um asterisco `*` em todos os 5 campos: Minute, Hour, Day, Month, Weekday).*

Isso garante que o Laravel cheque o relógio a cada minuto, mas ele só vai rodar o comando dos pontos à meia-noite, conforme programado no código.

---

## 2. `points:migrate-legacy`

Migra pontos legados (sem data_expiracao) de forma transparente, criando um par de Débito + Novo Crédito.

### Descrição

Busca todos os pontos de crédito ativos que não possuem `data_expiracao` preenchida e que ainda possuem saldo.
Para evitar que clientes percam pontos inesperadamente (por receberem uma data retroativa e já entrarem expirados), o comando:
1. Faz uma **baixa (Débito)** no crédito antigo.
2. Cria um **novo Crédito** com o mesmo saldo e com a `data_expiracao` contando a partir da data de execução (HOJE).

### Uso

**Simulação (Dry-run):**
```bash
php artisan points:migrate-legacy --dry-run
```
*(Não altera o banco de dados. Apenas lista o que seria feito no console).*

**Migração Real:**
```bash
php artisan points:migrate-legacy
```

### Comportamento

- Verifica se a opção `pontos_dias_expiracao` está configurada
- Itera sobre todos os tenants (multi-tenancy)
- Para cada tenant, busca pontos que:
  - São do tipo `credito`
  - Estão com status `ativo`
  - Têm saldo restante (`valor > valor_usado`)
  - NÃO têm `data_expiracao` preenchida
- Calcula a nova data de expiração: `hoje + pontos_dias_expiracao`
- Executa a transação gerando o par DÉBITO e NOVO CRÉDITO no extrato.

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

1. **Primeira vez (Migração)**: Rode `php artisan points:migrate-legacy` para regularizar pontos antigos que não tinham expiração.
2. **Rotina diária**: O comando `points:expire` roda automaticamente todo dia às 00:00
3. **Execução manual**: Rode `points:expire` manualmente quando necessário

---

## Troubleshooting

### "Configuração pontos_dias_expiracao não definida"

O comando de migração requer que a opção `pontos_dias_expiracao` esteja configurada nas opções de sistema.

### "Nenhum tenant encontrado"

O sistema não encontrou tenants para processar. Verifique se existem tenants cadastrados.

### Falha ao enviar notificação

Verifique se:
- O canal Brevo está configurado corretamente
- Os usuários com `permission_id = 1` existem e têm email válido
- O modelo User está disponível no contexto atual

---

## ⚠️ Guia de Deploy em Produção (Atualização PEPS)

> **ATENÇÃO**: Após o deploy da atualização que introduziu o rastreamento PEPS de consumo de créditos
> (`valor_usado` nos créditos), é **obrigatório** executar o processo de normalização abaixo.
> Executar apenas `points:expire` sem essa normalização irá gerar registros de expiração **incorretos**
> (com valores maiores do que o real, pois o sistema vai ignorar resgates já feitos anteriormente).

### Por que isso é necessário?

**Antes da atualização:** Os resgates criavam apenas um registro de débito, mas **não atualizavam** o campo `valor_usado` nos créditos correspondentes.

**Depois da atualização:** O sistema passou a rastrear exatamente quais créditos foram consumidos (lógica PEPS completa), atualizando `valor_usado` em tempo real.

Resultado: os créditos antigos têm `valor_usado = 0`, então o sistema de expiração acredita que 100% do crédito ainda está disponível, mesmo que já tenha sido gasto.

### Sequência obrigatória no servidor de produção

Execute os comandos **nesta ordem exata**, via SSH:

```bash
# 1. Entrar no diretório do projeto
cd /caminho/para/yellow-backend

# 2. Baixar o código mais recente
git pull

# 3. Limpar caches
php artisan config:clear
php artisan cache:clear

# 4. Aplicar a migration que altera a coluna 'tipo' de ENUM para STRING
php artisan tenants:migrate

# 5. Normalizar os dados históricos (pode demorar alguns minutos)
#    Este script:
#    - Remove expirações lançadas incorretamente
#    - Recalcula valor_usado de todos os créditos via PEPS histórico
#    - Deixa os créditos vencidos com status=ativo para serem processados pelo points:expire
php scripts/normalize_points.php

# 6. Definir data de expiração para créditos antigos que não possuem data_expiracao
#    Ao invés de atualizar retroativamente, criamos pares Debito/Credito com data a partir de hoje
php artisan points:migrate-legacy --dry-run 
# Verifique a saída e se tudo estiver OK, rode definitivamente:
php artisan points:migrate-legacy

# 7. Criar os registros de expiração corretos com base nos saldos reais (pontos que já venceram de verdade)
php artisan points:expire
```

### O que o script `normalize_points.php` faz

| Passo | Ação |
|-------|------|
| 1 | Remove registros de expiração existentes (podem estar errados) |
| 2 | Zera `valor_usado` em todos os créditos para recalcular do zero |
| 3 | Percorre todos os débitos históricos em ordem cronológica e aplica o PEPS |
| 4 | Marca créditos vencidos com `status = expirado` e `valor_usado = valor` |

> **Segurança:** Todo o processo roda dentro de uma transação de banco de dados.
> Se qualquer passo falhar, os dados voltam ao estado original automaticamente.

### Tempo estimado

- Depende do número de clientes e transações
- Para ~1.900 clientes com ~3.000 transações: aproximadamente **2 minutos**
- Para bases maiores, planeje uma janela de manutenção

### Verificação pós-deploy

Após executar todos os passos, verifique:
1. Nenhum cliente deve ter saldo muito negativo sem justificativa
2. Os registros de expiração devem mostrar apenas o saldo **real** que expirou (não o valor total do crédito quando já havia sido parcialmente gasto)
3. A coluna "Saldo Disp." nos extratos deve refletir o uso real dos créditos
