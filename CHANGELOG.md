# Changelog

Todas as mudanças relevantes deste projeto são registradas neste arquivo.

O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o
versionamento segue o [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [1.0.0] - 2026-11-03

### Adicionado

- Estrutura Laravel 12 com AdminLTE 4 modular (`header`, `navbar`, `sidebar`, `footer`, `scripts`, `app`) e layout responsivo em pt-BR.
- Middleware global `DeveloperSignatureMiddleware` com os cabeçalhos `X-Developer-Name`, `X-Developer-Company`, `X-Developer-CNPJ` e `X-System-Author`.
- Cabeçalho de autoria obrigatório em todos os arquivos PHP, Blade, JS, CSS, migrations e seeders.
- Tabela `app_settings` com grupos, chave única, suporte a segredos e criptografia AES-256 via `Crypt::encryptString`/`Crypt::decryptString`.
- `AppSetting::get()` com cache de 24 horas e `AppSetting::set()` com invalidação automática do cache.
- Seeder de configurações com endpoint `ovh-eu`, credenciais OVHcloud (AK/AS/CK) criptografadas, spread 2,00%, IOF 3,38%, ICMS 0,00% e outros 0,00%.
- Tabela `invoices` com valor original, cotação, spread, IOF, ICMS, outros tributos, total em BRL, status, vencimento, bloqueio de cotação e data de cálculo.
- `ExchangeRateService` integrado à AwesomeAPI (USD-BRL e EUR-BRL), cache de 15 minutos, timeout configurável e fallback para a última cotação conhecida.
- `TaxCalculationService` com a fórmula completa: base em BRL, spread, base com spread, tributos e total arredondado em duas casas.
- `OvhService` com API v1 (AK/AS/CK) para serviços ativos e faturas (`/me/bill`) e suporte a Bearer token da API v2.
- Comando `finance:sync-invoices` com `--import` e `--fresh`: atualiza cotações, importa faturas da OVHcloud e recalcula pendentes.
- Agendamento da sincronização a cada 15 minutos e alerta quando a variação cambial ultrapassa 1%.
- Evento `InvoiceAmountUpdatedEvent` transmitido via Laravel Reverb e notificação `InvoiceRateVariationNotification` por e-mail, banco e broadcast.
- Dashboard com cards de pendências, pagos no mês, vencidas e cotações, gráficos Chart.js e atualização em tempo real.
- CRUD de faturas com prévia de cálculo, bloqueio de cotação ao pagar e exportação CSV (BOM UTF-8, separador `;`, colunas em ordem alfabética e fórmulas neutralizadas).
- Gestão de usuários e permissões com os perfis `DEVELOPER_ADMIN`, `FINANCIAL_MANAGER` e `VIEWER`, proteções contra autodemissão e remoção do último administrador.
- Central de notificações, edição de perfil, troca de senha e páginas de erro 403, 404, 419, 500 e 503 em pt-BR.
- Tema claro/escuro com tokens de cor, `color-scheme`, `base-select` e favicon compartilhado.
- PWA com `manifest.json`, service worker (network-first para navegação e stale-while-revalidate para assets) e página offline.
- Ícones 192/512, `apple-touch-icon`, favicon SVG/ICO e `theme-color` para instalação no Windows e Android.
- Traduções pt-BR completas de validação, autenticação, senhas e paginação.
- Suíte de testes cobrindo cálculo tributário, criptografia de configurações, autenticação, fluxo de faturas, exportação CSV e sincronização com a AwesomeAPI (18 testes, 64 asserções).
- Documentação de instalação, operação, PWA e produção no `README.md`.

### Alterado

- Alvo inicial Laravel 11 substituído por Laravel 12.71.0 por avisos de segurança e fim de suporte da série 11; nenhuma regra de negócio foi afetada.
- Autenticação e permissões passam a validar `is_active` no login e nas rotas protegidas.

### Segurança

- Credenciais OVHcloud gravadas apenas no banco, criptografadas com a `APP_KEY`; nunca em `.env`, código ou Git.
- Arquivo `database/seeders/data/credentials.local.json` e o banco SQLite local mantidos fora do controle de versão.
- Tokens Bearer da API v2 não são persistidos em texto puro e expiram rapidamente por desenho da OVHcloud.

### Observações

- A API v2 (`https://api.eu.ovhcloud.com/v2`) usa OAuth2 com tokens de curta duração; a conciliação de faturas usa a API v1 com AK/AS/CK.
- Em produção, mantenha `FINANCE_VERIFY_SSL=true` e `APP_DEBUG=false`.

[1.0.0]: https://kdkhost.com.br/
