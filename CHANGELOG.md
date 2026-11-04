# Changelog

Todas as mudanças relevantes deste projeto são registradas neste arquivo.

O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o
versionamento segue o [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [1.2.0] - 2026-11-04

### Adicionado

- Tabela `license_services` e model `LicenseService`: cadastro das licenças compradas da LicenseBoy com fornecedor, produto, servidor, quantidade, valor unitário, moeda, ciclo, vencimento, situação e renovação automática.
- Chave da licença criptografada no banco (`encrypted`) e exibida apenas mascarada nas telas; campo em branco na edição preserva a chave atual.
- Página **Licenças** (`/licencas`) com licenças ativas, custo mensal e projeção anual em BRL, renovações próximas e valores individuais de cada serviço.
- CRUD completo de licenças com busca de produto, presets de ciclo e resumo lateral do custo convertido.
- Geração de fatura a partir da licença: cria a fatura em USD/EUR, aplica spread, IOF, ICMS e demais tributos pela cotação atual, vincula à licença e avança o próximo vencimento em um ciclo.
- Coluna `license_service_id` em `invoices` (remoção da licença preserva a fatura) e vínculo visível na página da fatura.
- Tabelas `cron_jobs` e `cron_job_runs`: agendamentos, configuração, histórico de execuções, código de saída, duração e saída completa.
- Página **Central de cron** (`/crons`, exclusiva de desenvolvedor/administrador) com linha única da hospedagem, botão de cópia, sinal de vida do agendador, totais de execuções e falhas.
- CRUD de crons com comandos Artisan ou shell, expressões validadas, presets (1 min, 5 min, 15 min, hora, diário, semanal e mensal), fuso horário e ativação individual.
- Execução manual com feedback, bloqueio contra execução simultânea (`Cache::lock`), timeout configurável e captura de stdout/stderr.
- Comando `cron:run-due`, agendado a cada minuto com `withoutOverlapping` e `onOneServer`, que executa apenas os jobs vencidos e grava o heartbeat do agendador em `app_settings`.
- 17 novos testes de feature para licenças e central de cron (40 testes e 173 asserções no total).

### Alterado

- Menu lateral ganha **Licenças** (Operação) e **Central de cron** (Sistema, apenas desenvolvedor).
- Fatura passa a exibir a licença de origem quando gerada a partir de uma licença LicenseBoy.
- Configuração `finance.cron.timeout` adicionada para limitar a duração das execuções.
- README documenta o fluxo de licenças, a linha única do cron e o funcionamento do agendador.
- Versão do sistema atualizada para 1.2.0 em telas, PWA e documentação.

### Corrigido

- `CronJob::isDue()` e `nextRun()` passavam o fuso horário no construtor de `CronExpression` (segundo parâmetro é a fábrica de campos), fazendo toda expressão ser considerada inválida e nenhum cron agendado executar. O fuso agora é enviado aos métodos `isDue()` e `getNextRunDate()`.

### Observações

- A LicenseBoy não publica API de consulta; o cadastro é manual e a geração de fatura usa o mesmo motor tributário das faturas OVH.
- A central de cron executa comandos shell quando cadastrados; o acesso é restrito ao perfil `DEVELOPER_ADMIN`.
- O ambiente local precisa de `php artisan schedule:work` (ou da linha única no cron) para o heartbeat ficar ativo.
- O print dos serviços contratados da LicenseBoy ainda não foi enviado; as licenças reais serão cadastradas assim que ele for compartilhado.

## [1.1.0] - 2026-11-03

### Adicionado

- Tabela `ovh_services` e model `OvhServiceItem`: todos os serviços contratados na OVHcloud, com tipo, estado, renovação, datas e valor mensal em EUR.
- Tabela `ovh_ips` e model `OvhIp`: blocos de IPv4/IPv6, serviço vinculado, país, campus, RIR, adicional e DNS reverso.
- Tabela `invoice_items` e model `InvoiceItem`: linhas individuais de cada fatura (descrição, domínio, quantidade, valor unitário, total, moeda e período).
- `OvhInventoryService` com importação de serviços, IPs e itens, além do recálculo do valor mensal de cada serviço a partir da fatura mais recente.
- `OvhService::getBillItems()` e `OvhService::getIps()` com cache de 30 minutos, incluindo DNS reverso e detalhes por bloco.
- Página **Serviços OVH** (`/servicos`) com cards de totais, tabela de serviços com valores individuais em EUR e BRL, lista de IPs e última sincronização.
- Seção **Serviços contratados e valores individuais** no dashboard, com todos os serviços, total mensal e blocos de IP.
- Seção **Itens da fatura** na página de cada fatura, com o total das linhas.
- Link **Serviços OVH** no menu Operação da barra lateral.
- 5 novos testes de feature cobrindo página de infraestrutura, dashboard e itens de fatura (23 testes, 88 asserções no total).

### Alterado

- Importação da OVHcloud passa a trazer todo o histórico: 365 dias e até 100 faturas por padrão (`ovh_import_days` e `ovh_import_limit` no painel).
- Após importar faturas, o comando sincroniza serviços, IPs e recalcula os valores mensais.
- Placeholders das credenciais AK/AS/CK passam a indicar "Chave salva · ••••xxxx" em vez de apenas os pontos.
- Versão do sistema atualizada para 1.1.0 em telas, PWA e documentação.

### Corrigido

- Listener do botão "Testar conexão" da OVH estava dentro do bloco da aba de regras e só funcionava nela; agora funciona em todas as abas.
- Consulta de detalhe de bloco de IP passa a codificar a barra do CIDR (`rawurlencode`), evitando 404 da API.

### Observações

- A OVHcloud não expõe status de pagamento nas faturas: meses anteriores são tratados como pagos e com câmbio travado; a fatura do mês corrente permanece pendente para conciliação interna.
- Valores mensais por serviço são derivados das linhas da fatura mais recente; serviços sem cobrança recente (ex.: Public Cloud sem consumo) aparecem como "—".
- Dados reais sincronizados nesta versão: 17 faturas históricas pagas, 102 itens, 6 serviços e 5 blocos de IP (custo mensal de € 43,96).

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

[1.2.0]: https://kdkhost.com.br/
[1.1.0]: https://kdkhost.com.br/
[1.0.0]: https://kdkhost.com.br/
