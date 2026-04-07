# Guia de Deploy para IA (Projeto Point Store - Yellow)

Este documento foi criado especificamente para ser lido e seguido por um Assistente de Inteligência Artificial para realizar ou auxiliar no deploy da aplicação "Point Store" em um servidor VPS (Linux, preferencialmente Ubuntu 22.04 ou 24.04).

## 1. Compreensão da Arquitetura do Projeto

O projeto está dividido em dois repositórios/diretórios principais:
- **Backend (`yellow-backend`)**: Uma API construída em **Laravel 12** utilizando **PHP 8.2+**. Este backend utiliza o pacote `stancl/tenancy` para arquitetura Multi-Tenant.
- **Frontend (`yellow-frontend`)**: Uma aplicação **React** construída com **Vite** e **TypeScript** (`tsx`).

## 2. Requisitos do Servidor VPS

A IA deve garantir que o servidor possui os seguintes softwares instalados:
- Nginx (Servidor Web)
- PHP 8.2 ou superior (com extensões: `fpm`, `mysql`/`pgsql`, `mbstring`, `xml`, `bcmath`, `curl`, `zip`, `dom`)
- Composer v2+
- Node.js (Recomendado v20+) e NPM
- Banco de Dados (MySQL 8+ ou MariaDB)
- Supervisor (Para gestão das filas do Laravel)
- Certbot (Para SSL/HTTPS)

## 3. Configuração de DNS (Atenção ao Multi-Tenant)

Devido ao uso do `stancl/tenancy`, o projeto geralmente requer identificação do inquilino (tenant) pelo domínio ou subdomínio.
- **Domínio do Frontend**: Apontamento tipo `A` normal (ex: `app.seudominio.com`).
- **Domínio do Backend (API)**: Apontamento tipo `A` curinga (Wildcard `*`) apontando para o IP da VPS (ex: `api.seudominio.com` e `*.api.seudominio.com`), para suportar subdomínios dinâmicos dos tenants.

## 4. Passo a Passo do Deploy

A IA deve executar as seguintes rotinas de comandos no diretório onde o projeto será clonado (Ex: `/var/www/point_store`):

### 4.1. Deploy do Backend (`yellow-backend`)
1. Acesse o diretório: `cd /var/www/point_store/yellow-backend`
2. Instalar dependências sem plugins de desenvolvimento: `composer install --optimize-autoloader --no-dev`
3. Copiar e configurar o `.env`:
   - `cp .env.example .env`
   - Configurar o banco de dados principal.
   - Configurar a variável pertinente aos domínios centrais do pacote de Tenancy (geralmente `TENANT_CENTRAL_DOMAIN`).
4. Gerar chave da aplicação: `php artisan key:generate`
5. Configurar permissões de pastas do Laravel:
   - `chown -R www-data:www-data storage bootstrap/cache`
   - `chmod -R 775 storage bootstrap/cache`
6. Criar o link simbólico de storage: `php artisan storage:link`
7. Executar as migrações principais (e/ou dos tenants, conforme configuração): `php artisan migrate --force`

### 4.2. Deploy do Frontend (`yellow-frontend`)
1. Acesse o diretório: `cd /var/www/point_store/yellow-frontend`
2. Configurar o `.env` de produção:
   - Definir a variável para forçar a API em produção de forma dinâmica/correta:
     `VITE_TENANT_API_URL="https://[seu-dominio-base-da-api]/api"`
3. Instalar pacotes NPM: `npm install`
4. Gerar a build estática: `npm run build`
   - Certifique-se de que os arquivos foram gerados na pasta `yellow-frontend/dist`.

### 4.3. Configuração do Servidor Web (Nginx)

A IA deve criar os blocos de servidor (Server Blocks) no diretório `/etc/nginx/sites-available/` e criar links para `sites-enabled/`.

**A) Bloco do Backend (API):**
- Deve apontar o `root` para `/var/www/point_store/yellow-backend/public`.
- O `server_name` deve refletir o curinga e o domínio principal da API. Exemplo:
  `server_name api.seudominio.com *.api.seudominio.com;`
- O bloco de roteamento deve ser o padrão Laravel: `try_files $uri $uri/ /index.php?$query_string;`
- Configurar bloco FastCGI para o `php-fpm`.

**B) Bloco do Frontend (React / Vite):**
- Deve apontar o `root` para `/var/www/point_store/yellow-frontend/dist`.
- O bloco de roteamento deve focar em Fallback de SPA (React Router): `try_files $uri $uri/ /index.html;`

### 4.4. Fila e Workers (Supervisor)

A IA deve ser instruída a configurar os workers de plano de fundo fundamentais para o sistema.
1. Criar um arquivo `/etc/supervisor/conf.d/pointstore-worker.conf`.
2. Utilizar o comando `php /var/www/point_store/yellow-backend/artisan queue:listen` (ou `queue:work --tries=3`).
3. Atualizar o supervisor: `supervisorctl update` e `supervisorctl start pointstore-worker:*`.

### 4.5. Configuração SSL (HTTPS)

- Para o Frontend: Certificado SSL comum usando `certbot --nginx -d app.seudominio.com`.
- Para o Backend (por ser Multi-Tenant e exigir o wildcard `*.api.*`): Será necessário rodar o Certbot com o desafio **DNS-01**.
  Exemplo de comando: `certbot certonly --manual --preferred-challenges dns -d "api.seudominio.com" -d "*.api.seudominio.com"`

---

**FIM DAS INSTRUÇÕES.**
A IA encarregada do Deploy agora tem as diretrizes arquiteturais necessárias para este projeto rodando corretamente e sem interrupções.
