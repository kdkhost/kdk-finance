# KDK Finance — Multimoedas

Sistema financeiro para conciliação de faturas internacionais (EUR/USD → BRL), gestão de tributos de importação e acompanhamento das faturas da OVHcloud.

- **Desenvolvedor:** George Marcelo de Oliveira Pimentel
- **Empresa:** KDKHost Soluções — CNPJ 33.986.975/0001-90
- **Contato:** contato@kdkhost.com.br · https://kdkhost.com.br
- **Versão:** 1.0.0 · Laravel 12.71.0 · PHP 8.3

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
- CRUD de faturas com prévia do cálculo em tempo real, pagamento, cancelamento, reabertura, recálculo e bloqueio de cotação.
- Importação automática das faturas recentes da OVHcloud e sincronização agendada a cada 15 minutos.
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
php artisan finance:sync-invoices --import   # importa faturas da OVHcloud
php artisan finance:sync-invoices --import --fresh
```

### Agendador

A sincronização roda a cada 15 minutos. Em produção, adicione ao cron:

```cron
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

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
fluxo de faturas, exportação CSV e sincronização com a AwesomeAPI.

---

## Estrutura

```
app/
├── Console/Commands/SyncInvoicesCommand.php
├── Events/InvoiceAmountUpdatedEvent.php
├── Http/Controllers/          # Dashboard, Faturas, Configurações, Usuários, Perfil, Notificações
├── Http/Middleware/           # Assinatura do desenvolvedor e controle de perfil
├── Models/                    # AppSetting, Invoice, User
├── Notifications/             # Variação cambial
└── Services/                  # ExchangeRate, TaxCalculation, Ovh, Dashboard

resources/
├── css/theme.css              # Tokens claro/escuro e componentes
├── js/                        # Bootstrap, Echo, Dashboard, formulário de fatura
└── views/
    ├── admin/layouts/         # AdminLTE 4 modular
    ├── auth/ errors/ invoices/ notifications/ profile/ settings/ users/
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
