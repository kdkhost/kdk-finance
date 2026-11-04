# KDK Finance — Multimoedas

Sistema financeiro para conciliação de faturas internacionais (EUR/USD → BRL), gestão de tributos de importação, acompanhamento das faturas da OVHcloud, controle das licenças LicenseBoy e central de cron gerenciada pelo painel.

- **Desenvolvedor:** George Marcelo de Oliveira Pimentel
- **Empresa:** KDKHost Soluções — CNPJ 33.986.975/0001-90
- **Contato:** contato@kdkhost.com.br · https://kdkhost.com.br
- **Versão:** 1.3.0 · Laravel 12.71.0 · PHP 8.3

---

## Visão geral

O sistema converte valores em euro e dólar para real com a fórmula completa de custo Brasil:

```
base_brl            = valor_original × cotação
spread              = base_brl × spread%
base_com_spread     = base_brl + spread
iof / icms / outros = base_com_spread × respectivo%
total_brl           = base_com_spread + iof + icms + outros
```

As cotações vêm da AwesomeAPI (USD-BRL e EUR-BRL), ficam em cache por 15 minutos e,
se a API falhar, o sistema reaproveita a última cotação válida — marcada como
"última cotação conhecida" na interface.

### Recursos principais

- Dashboard com pendências, pagos no mês, faturas vencidas, composição da carteira e evolução financeira.
- Inventário completo da OVHcloud no painel: serviços contratados, valores mensais individuais (EUR e BRL) e blocos de IPv4/IPv6 com DNS reverso.
- Itens individuais de cada fatura importada (descrição, domínio, quantidade, valor unitário, total e período).
- CRUD de faturas com prévia do cálculo em tempo real, pagamento, cancelamento, reabertura, recálculo e bloqueio de cotação.
- CRUD de licenças LicenseBoy (cPanel, CloudLinux, LiteSpeed e demais produtos), com custo mensal em BRL, renovações, chave criptografada e geração de fatura em um clique.
- Catálogo de preços em tempo real da loja LicenseBoy (37 produtos nas 8 categorias públicas), com sincronização diária e licenças vinculadas que acompanham o preço da loja.
- Central de cron com CRUD de agendamentos, presets, fuso horário, execução manual, histórico de saídas e sinal de vida do agendador — apenas uma linha no cron da hospedagem.
- Importação automática de todo o histórico de faturas da OVHcloud e sincronização agendada a cada 15 minutos.
- Atualização em tempo real do dashboard via Laravel Reverb (WebSocket).
- Alerta por e-mail, banco e broadcast quando a variação cambial ultrapassa 1%.
- Exportação CSV organizada (BOM UTF-8, separador `;`, colunas em ordem alfabética e fórmulas neutralizadas).
- Perfis de acesso: desenvolvedor/administrador, gestor financeiro e consulta.
- PWA instalável no Windows e Android, com página offline.
- Tema claro e escuro, interface integralmente em português do Brasil.

---

## Requisitos

| Item | Versão mínima |
| --- | --- |
| PHP | 8.2 (testado em 8.3.28) |
| Composer | 2.x |
| Node.js | 20.x (testado com Vite 7) |
| Banco | SQLite (padrão), MySQL ou PostgreSQL |
| Extensões PHP | `openssl`, `pdo`, `mbstring`, `fileinfo`, `curl`, `gd` (para gerar ícones) |

---

## Instalação

```bash
# 1. Dependências
composer install
npm install

# 2. Ambiente
cp .env.example .env
php artisan key:generate

# 3. Banco e dados iniciais
php artisan migrate --seed

# 4. Front-end
npm run build

# 5. Servidor local
php artisan serve
```

Acesse `http://127.0.0.1:8000`.

### Variáveis de ambiente

O `.env` guarda apenas infraestrutura (banco, cache, sessão, fila, Reverb, `APP_KEY`).
**Nenhuma credencial operacional ou taxa é lida do `.env`** — tudo fica na tabela
`app_settings` e é editável no painel.

Chaves financeiras relevantes:

```dotenv
FINANCE_VERIFY_SSL=true   # false apenas em ambiente local sem bundle CA (WAMP)
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=reverb
```

---

## Contas iniciais

O `UserSeeder` cria três perfis. As senhas de demonstração ficam em
`database/seeders/data/credentials.local.json`, que é **ignorado pelo Git**.
Sem esse arquivo, o seeder gera senhas aleatórias e as imprime no terminal.

| Perfil | E-mail | Permissões |
| --- | --- | --- |
| Desenvolvedor / Administrador | `george@kdkhost.com.br` | Acesso total, credenciais OVH, usuários e regras |
| Gestor financeiro | `financeiro@kdkhost.com.br` | Câmbio, tributos, faturas e conciliação |
| Consulta (somente leitura) | `consulta@kdkhost.com.br` | Leitura de painel, faturas e notificações |

> Troque as senhas no primeiro acesso em **Meu perfil**.

---

## Credenciais OVHcloud

As chaves são gravadas **criptografadas** em `app_settings` (AES-256 com a `APP_KEY`)
e nunca vão para o Git.

- **Painel:** Configurações → OVHcloud.
- **Campos em branco preservam** o valor atual; use a opção "Limpar credenciais" para remover.
- **Testar conexão** consulta o nichandle e a quantidade de serviços ativos.

### API v1 (usada na conciliação)

A conciliação de faturas usa a API legada com três chaves, criadas em
https://api.ovh.com/createToken/ :

- `GET /me/*`
- `GET /me/bill/*`
- `GET /service`
- `GET /service/*`

### API v2 (OAuth2)

O console https://api.eu.ovhcloud.com/console/ gera tokens Bearer de **curta duração**
para `https://api.eu.ovhcloud.com/v2`. Tokens expirados retornam `401`; gere um novo
quando necessário. O sistema aceita o token para consultas v2, mas a importação de
faturas continua usando AK/AS/CK (v1), que não expiram.

> Nunca grave tokens v2 em código, arquivo ou repositório.

---

## Operação

### Sincronização manual

```bash
php artisan finance:sync-invoices            # cotações + recálculo
php artisan finance:sync-invoices --import   # importa faturas, itens, serviços e IPs da OVHcloud
php artisan finance:sync-invoices --import --fresh
```

A importação traz **todo o histórico** configurado em Configurações → OVHcloud
(365 dias e 100 faturas por padrão), grava as linhas individuais de cada fatura,
sincroniza os serviços contratados e os blocos de IP. A OVHcloud não expõe um
campo de "pago" nas faturas: faturas de meses anteriores são marcadas como pagas
e com câmbio travado; a fatura do mês corrente permanece pendente para conciliação.

### Infraestrutura e valores individuais

Em **Serviços OVH** (menu Operação) ficam:

- todos os serviços contratados, com tipo, estado, renovação e valor mensal em EUR e BRL;
- os blocos de IPv4/IPv6, serviço vinculado e DNS reverso;
- o custo mensal total da conta, convertido pela cotação atual.

O valor mensal de cada serviço é extraído da fatura mais recente em que ele
aparece, somando as linhas do mesmo domínio. O dashboard repete o resumo com os
mesmos valores e a página de cada fatura exibe a tabela **Itens da fatura**.

### Licenças LicenseBoy

As licenças são compradas da **LicenseBoy**, que vende pelo WHMCS e não publica API
de consulta. O controle fica em **Licenças** (menu Operação):

- cadastro por produto (cPanel, DirectAdmin, Plesk, CloudLinux, LiteSpeed,
  Virtualizor, Imunify360, KernelCare, Softaculous, JetBackup, CXS, OSM, SitePad,
  WHMReseller, CPNginx e nomes livres), com servidor, quantidade e valor unitário;
- ciclo mensal, trimestral, semestral, anual ou vitalício e próximo vencimento;
- chave da licença criptografada no banco e exibida apenas mascarada;
- custo mensal e projeção anual em BRL pela cotação atual;
- geração de fatura em um clique: cria a fatura em USD/EUR, aplica spread, IOF,
  ICMS e demais tributos, vincula à licença e avança o próximo vencimento em um
  ciclo (licenças vitalícias não avançam).

#### Catálogo de preços da loja

**Preços da loja** (`/licencas/precos`) lê as oito categorias públicas da loja da
LicenseBoy e mantém um catálogo local de 37 produtos com preço, moeda, ciclo,
setup, estoque e o selo "a partir de":

- Painéis de controle, CloudLinux, LiteSpeed, WHMCS, Addons e extensões, Bundles,
  Configuração e gestão e Revenda;
- **Atualizar preços** no painel ou `php artisan licenses:sync-prices`; o agendador
  também roda diariamente às 05:10;
- ao vincular uma licença a um item do catálogo, o preço, a moeda e o ciclo passam
  a seguir a loja e são reaplicados a cada sincronização;
- categorias que falharem na leitura são preservadas como estavam — só produtos de
  categorias lidas com sucesso podem ser removidos.

### Central de cron

Em **Central de cron** (menu Sistema, exclusivo de desenvolvedor/administrador)
todos os agendamentos ficam no banco. A hospedagem recebe **uma única linha**:

```cron
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

O comando `cron:run-due` roda a cada minuto, verifica as expressões cadastradas,
executa as que estão vencidas e grava histórico com código de saída, duração e
saída completa. Cada job aceita comando Artisan ou shell, expressão cron com
presets, fuso horário, ativação/desativação e execução manual. O painel mostra
um sinal de vida: se o agendador não rodar por 3 minutos, o status fica
"Sem sinal".

### Agendador

O `schedule:run` dispara a sincronização da OVH a cada 15 minutos, a central de
cron a cada minuto e o catálogo de preços da LicenseBoy diariamente às 05:10.
Em produção, adicione ao cron exatamente a linha única acima.

### Filas

Notificações usam a fila `database`:

```bash
php artisan queue:work --tries=3
```

### Tempo real (Reverb)

```bash
php artisan reverb:start
```

As credenciais do Reverb ficam no `.env` (infraestrutura) e são expostas ao front-end
via `VITE_REVERB_*`. Sem o Reverb, o sistema continua funcionando — apenas sem push
em tempo real; o dashboard cai no carregamento normal.

### PWA

Após `npm run build` e com o site em HTTPS (ou `localhost`), instale pelo botão de
instalação do navegador. O service worker faz:

- navegação: network-first com fallback para `offline.html`;
- assets: stale-while-revalidate;
- exclusão de rotas dinâmicas (`/app/`, `/broadcasting/`, `/api/`, `/sanctum/`).

Para regenerar ícones e favicons:

```bash
php tools/generate-icons.php
```

---

## Testes

```bash
php artisan test
```

Cobertura atual: cálculo tributário, criptografia de configurações, autenticação,
fluxo de faturas, exportação CSV, sincronização com a AwesomeAPI, página de
serviços/IPs do painel, itens individuais de fatura, CRUD de licenças com
criptografia e geração de fatura, central de cron (permissões, validação,
execução manual, agendamento por minuto e heartbeat) e catálogo de preços da
LicenseBoy (sincronização, remoção de itens, falha por categoria, permissões,
prefill e vínculo que prevalece sobre o formulário). São 48 testes e 207 asserções.

---

## Estrutura

```
app/
├── Console/Commands/          # SyncInvoicesCommand, RunDueCronsCommand e SyncLicensePricesCommand
├── Events/InvoiceAmountUpdatedEvent.php
├── Http/Controllers/          # Dashboard, Faturas, Infraestrutura, Licenças, Crons, Configurações, Usuários, Perfil, Notificações
├── Http/Middleware/           # Assinatura do desenvolvedor e controle de perfil
├── Models/                    # AppSetting, Invoice, InvoiceItem, OvhServiceItem, OvhIp, LicenseService, LicenseCatalogItem, CronJob, CronJobRun, User
├── Notifications/             # Variação cambial
└── Services/                  # ExchangeRate, TaxCalculation, Ovh, OvhInventory, Dashboard, CronRunner, LicenseBoyPrice

resources/
├── css/theme.css              # Tokens claro/escuro e componentes
├── js/                        # Bootstrap, Echo, Dashboard, formulário de fatura
└── views/
    ├── admin/layouts/         # AdminLTE 4 modular
    ├── auth/ errors/ infrastructure/ invoices/ licenses/ crons/ notifications/ profile/ settings/ users/
    └── partials/

public/                        # manifest.json, sw.js, offline.html, ícones, favicon
database/                      # Migrations, seeders e SQLite local
lang/pt_BR/                    # Validação, autenticação, senhas e paginação
tests/Feature/                 # Suíte de testes
```

---

## Produção

1. `APP_ENV=production`, `APP_DEBUG=false` e `APP_URL` com HTTPS.
2. `FINANCE_VERIFY_SSL=true`.
3. `php artisan migrate --force`, `npm run build`, `php artisan optimize`.
4. Backup seguro da `APP_KEY` — sem ela as credenciais OVH não podem ser descriptografadas.
5. Cron do agendador e um supervisor para `queue:work` e `reverb:start`.
6. Nunca versione `.env`, `credentials.local.json`, banco local nem tokens.

---

## Créditos

Desenvolvido por **George Marcelo de Oliveira Pimentel** para a
**KDKHost Soluções** (CNPJ 33.986.975/0001-90).

- Site: https://kdkhost.com.br
- E-mail: contato@kdkhost.com.br

Consulte também o [CHANGELOG](CHANGELOG.md).
