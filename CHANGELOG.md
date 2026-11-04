# Changelog

Todas as mudanças relevantes deste projeto são registradas neste arquivo.

O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o
versionamento segue o [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [1.5.3] - 2026-11-04

### Alterado

- **Redesenho da página de conversões** (`/conversoes`): o topo agora mostra a cotação ao vivo em destaque, com a taxa inversa, a fonte, o horário da consulta, as tarifas padrão e um atalho para as configurações de conversão.
- Faixa de resumo com total convertido, quantidade de conversões, spread médio e equivalente em reais, exibida apenas quando há registros.
- Filtros compactados em uma barra única, sem o cartão pesado anterior.
- Estado vazio refeito: mensagem centralizada, exemplo real (€ 43,96 → US$ 51,73) e chamada para criar a primeira conversão, em vez de quatro cartões zerados e uma tabela vazia.
- Tabela oculta quando não há conversões nem filtros, deixando a tela limpa; a tabela continua completa em desktop e mobile quando há dados.
- Layout verificado nos temas claro e escuro e em telas estreitas.

## [1.5.2] - 2026-11-04

### Adicionado

- **Cotação do provedor** (PayPal, banco, emissor) nas conversões: informe a taxa que o recibo mostra e o sistema deriva o spread real sobre a cotação de mercado, travando o campo de spread enquanto a taxa estiver preenchida.
- O recibo do PayPal traz a taxa na direção inversa do formulário (`1 USD = 0,8498 EUR` num pagamento em euro); o sistema detecta, converte para a direção da conversão e avisa na própria tela. Exemplo validado: € 43,96 a 1,12584 de mercado e 0,8498 do recibo resultam em **US$ 51,73**, exatamente o valor cobrado pelo PayPal.
- Página de detalhe mostra a cotação do provedor, o spread derivado e, quando houve inversão, a taxa originalmente informada.
- O **recalcular** rederiva o spread a partir da cotação do provedor gravada, mantendo o total alinhado à taxa que o provedor pratica mesmo quando o mercado se move.
- 5 novos testes: spread derivado, recibo do PayPal na direção inversa, spread negativo na mesma direção, recálculo preservando a cotação do provedor e exibição na tela de detalhe. São 76 testes e 342 asserções no total.

### Alterado

- Spread aceita valor negativo quando o provedor entrega menos moeda de destino por unidade do que o mercado; a validação do formulário acompanha essa possibilidade.
- README documenta a cotação do provedor e a interpretação do recibo do PayPal.

## [1.5.1] - 2026-11-04

### Adicionado

- **VET (Valor Efetivo Total)** exibido nas conversões e nas faturas: mostra quantos reais custou cada unidade da moeda de origem já com câmbio, spread, IOF, tarifa e margem — a mesma taxa efetiva que o app do banco apresenta.
- Prévia da conversão e prévia da fatura calculam o VET em tempo real; a tela salva e o detalhe da fatura exibem o VET com 4 a 6 casas.
- Explicação no painel de que o VET não é uma taxa a cadastrar, e sim o resultado do cálculo.

### Alterado

- IOF padrão das conversões passa de 0% para **3,50%**, acompanhando a tarifa mostrada pelo banco; a orientação para usar 0% quando o saldo em dólar já foi abastecido com IOF continua visível.
- README documenta o VET e a nova orientação do IOF.

## [1.5.0] - 2026-11-04

### Adicionado

- **Sincronização com o painel de cliente da LicenseBoy** (`licenses:sync-panel`): lê os serviços ativos, o valor recorrente, a moeda, o ciclo, o próximo vencimento e a situação diretamente da área do cliente, com login por token CSRF e cookies de sessão.
- Credenciais do painel LicenseBoy gravadas em `app_settings` (e-mail em texto, senha criptografada com `Crypt::encryptString`), configuráveis em **Configurações → LicenseBoy**, com botão de sincronização manual e opção de apagar as credenciais.
- O **valor do painel passa a prevalecer** sobre o preço da loja no cálculo mensal, nas faturas geradas e nos indicadores em BRL; a loja continua exibida apenas como referência.
- Campos **Valor no painel**, **Moeda do painel** e **Ciclo do painel** no formulário da licença, para ajuste manual quando o suporte da LicenseBoy alterar a cobrança recorrente (o painel do cliente não permite editar esse valor).
- Cartão **Painel LicenseBoy** na tela da licença com valor cobrado, ciclo, total do ciclo, preço da loja, divergência em percentual e o custo extra por mês e por ano.
- Agendamento diário da sincronização do painel às 05:25, sem sobreposição e em um único servidor.
- 7 novos testes de feature para o painel: leitura de preço decimal (7.99 e 1.80 sem virar 799/180), gravação e uso do valor do painel na fatura, credenciais criptografadas e removíveis, permissão de consulta, empate de produtos ignorado, prevalência do painel sobre a loja e ciclo do painel definindo o equivalente mensal. São 70 testes e 310 asserções no total.

### Corrigido

- Preços do painel como `7.99` eram interpretados como `799`; agora ponto sozinho é decimal e vírgula segue o padrão brasileiro (`1.234,56`).
- Ciclo **Mensal** do painel não era reconhecido e ficava vazio; a leitura passou a considerar “mensal”, “mês” e “mes”.
- Licenças com serviços antigos cancelados no painel não eram vinculadas; o vínculo agora prioriza o serviço ativo quando o nome ou o produto se repete.
- Segredos apagados em `app_settings` não geram mais aviso de descriptografia e voltam ao valor padrão.

### Alterado

- **Cabeçalho fixo** em todas as telas do painel: fica preso ao topo (`position: sticky`), acima do conteúdo (`z-index` corrigido sobre a regra da AdminLTE) e ganha sombra suave ao rolar.
- Tema escuro padronizado em todas as páginas: fundo da página mais profundo que os cartões, tabelas, modais, menus, campos e seletores com as mesmas cores, sem painéis claros residuais.
- Tabelas de licenças, serviços (infraestrutura e painel), usuários e catálogo de preços reorganizadas no mobile: colunas secundárias ocultas por breakpoint e informações essenciais reunidas sob o nome, sem rolagem horizontal nem barra interna.
- README documenta a sincronização do painel, a precedência de preços, o agendamento e as tabelas responsivas.

## [1.4.0] - 2026-11-04

### Adicionado

- CRUD de **Conversões** (`/conversoes`): calcula quantos dólares são necessários para pagar um valor em euro e o caminho inverso, com histórico das simulações salvas.
- Cotação direta EUR-USD pela AwesomeAPI (`/json/last/EUR-USD`), sem chave e sem custo, com cache de 5 minutos; se o par direto falhar, o sistema cruza as cotações BRL (EUR-BRL ÷ USD-BRL) e, em último caso, reaproveita a última cotação conhecida marcada como desatualizada.
- `CurrencyConversionService`: aplica spread, IOF, tarifa fixa e margem de segurança de forma sequencial, arredondando cada etapa em duas casas — a mesma lógica do cálculo tributário das faturas.
- Tarifas do cartão internacional configuráveis no painel (**Configurações → Conversão e cartão**), gravadas em `app_settings` no grupo `conversion`: spread, IOF, tarifa fixa por operação e margem de segurança.
- Aviso explícito de que o IOF de 3,5% de 2026 incide **uma única vez** (cartão internacional ou aporte na conta global), com valor padrão 0% para não contar o imposto duas vezes em conta já abastecida.
- Prévia do cálculo em tempo real no formulário: base, spread, subtotal, IOF, tarifa fixa, margem, total, taxa efetiva, custo percentual e equivalente em BRL.
- Botão **Atualizar cotação** que busca o par atual sem recarregar a página e informa a origem (AwesomeAPI, cruzamento BRL, última cotação conhecida ou valor informado manualmente) e o horário da leitura.
- Botão de inversão de moedas que troca EUR↔USD e refaz a cotação automaticamente.
- Página de detalhe com composição completa, taxa efetiva, custo percentual, referência em BRL, origem e idade da cotação e alerta quando a cotação está desatualizada.
- **Conferência da fatura do cartão**: informa o valor realmente cobrado, o sistema grava o custo real percentual e mostra a diferença entre o calculado e o cobrado.
- Recalcular pela cotação atual mantendo os mesmos valores e tarifas, com aviso claro do antes e depois.
- Lista com filtros por direção (EUR→USD, USD→EUR), busca e somente conferidas, além de totais de conversões salvas, custo médio conferido e equivalente em BRL.
- Link **Conversões** no menu Operação e atalho **Nova conversão** para gestores financeiros.
- 15 novos testes de feature: cálculo completo, totais adulterados no POST ignorados, permissões de consulta, calibração, recálculo com nova cotação, endpoint de cotação, padrões configurados, par direto, cruzamento BRL, última cotação, padrão do sistema, cache de 5 minutos e moedas iguais. São 63 testes e 270 asserções no total.

### Alterado

- `ExchangeRateService` ganhou cotação de par direto (`pair()`), com cache próprio, cruzamento via BRL e persistência da última cotação válida em `app_settings`.
- Tela de configurações recebe a aba **Conversão e cartão**, com as tarifas, a cotação EUR→USD ao vivo e um exemplo do cálculo passo a passo.
- Tabela de conversões no mobile exibe direção, cobrança, valor de origem e cotação em bloco compacto, sem rolagem horizontal.
- README documenta o CRUD de conversões, a API gratuita, as tarifas e a conferência da fatura do cartão.
- Versão do sistema atualizada para 1.4.0 em telas, PWA e documentação.

## [1.3.1] - 2026-11-04

### Corrigido

- Lista de faturas no mobile: a tabela não força mais a rolagem horizontal. As colunas secundárias (Moeda, Valor original, Cotação) passam a aparecer apenas em telas maiores; Status e Vencimento aparecem a partir do breakpoint `md`.
- No mobile, cada fatura exibe situação, vencimento (em vermelho quando atrasada), câmbio travado, valor original e cotação em um bloco compacto sob a descrição, para não perder informação com as colunas ocultas.
- Descrição da fatura limitada a duas linhas no mobile e espaçamento das células reduzido, deixando as linhas visivelmente mais curtas.
- Filtro **Somente vencidas** alinhado aos demais campos e botões em todas as larguras (removido o espaçador fixo que desalinhava o controle e criava área morta).
- Tabela agora cabe integralmente no cartão em 360 px, 393 px, 768 px, 1024 px, 1200 px e 1440 px, sem rolagem horizontal e sem barra vertical interna.

### Alterado

- Versão do sistema atualizada para 1.3.1 em telas, PWA e documentação.

## [1.3.0] - 2026-11-04

### Adicionado

- Tabela `license_catalog_items`: catálogo local dos produtos públicos da loja da LicenseBoy com nome, slug, categoria, preço, moeda, ciclo, setup, estoque, URL e data da última leitura.
- Coluna `license_catalog_item_id` em `license_services`, vinculando cada licença ao produto correspondente da loja.
- `LicenseBoyPriceService`: leitura das oito categorias públicas da loja (`control-panel-licenses`, `shared-cloudlinux-licenses`, `shared-litespeed-licenses`, `whmcs-license`, `shared-licenses`, `bundles`, `server-config-and-management` e `reseller-packages`), interpretação dos cartões via DOMDocument/DOMXPath e atualização idempotente por `provider + slug`.
- Preços com selo "a partir de", setup fee, estoque ("0 Available") e ciclo; categorias que falharem na leitura são preservadas e só produtos de categorias lidas com sucesso podem ser removidos.
- Reaplicação automática do preço, moeda e ciclo do catálogo em todas as licenças vinculadas a cada sincronização.
- Página **Preços LicenseBoy** (`/licencas/precos`) com totais, última sincronização, licenças vinculadas, produtos esgotados, tabelas por categoria, link externo e botão **Atualizar preços** com confirmação.
- Ação "Usar este preço": abre o cadastro de licença com produto, identificação, valor, moeda e ciclo preenchidos a partir do item do catálogo (`?catalogo={id}`).
- Campo **Preço de referência** no formulário de licença, com agrupamento por categoria; ao vincular, o valor fica somente leitura e o catálogo prevalece sobre valores manipulados no formulário.
- Cartão **Catálogo LicenseBoy** na página da licença, com produto da loja, preço, disponibilidade, última leitura e aviso quando o valor salvo difere do catálogo.
- Comando `licenses:sync-prices`, agendado diariamente às 05:10 com `withoutOverlapping` e `onOneServer`, que sincroniza o catálogo e aplica os preços às licenças vinculadas.
- Link **Preços da loja** na página de licenças.
- 8 novos testes de feature para o catálogo (criação/atualização, remoção, falha por categoria, permissões, prefill, vínculo e comando). São 48 testes e 207 asserções no total.

### Alterado

- Textos de licenças passam a explicar que os preços vêm do catálogo público e que o vínculo acompanha a loja.
- README documenta o catálogo, a sincronização diária e o comando manual.
- Versão do sistema atualizada para 1.3.0 em telas, PWA e documentação.

### Observações

- A LicenseBoy usa WHMCS e não fornece API de consulta; a leitura é feita nas páginas públicas das categorias, sem credenciais e sem qualquer escrita na loja.
- Nenhum preço é gravado no `.env`: o catálogo fica no banco e a última leitura é registrada em `app_settings`.

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

[1.5.2]: https://kdkhost.com.br/
[1.5.1]: https://kdkhost.com.br/
[1.5.0]: https://kdkhost.com.br/
[1.4.0]: https://kdkhost.com.br/
[1.3.1]: https://kdkhost.com.br/
[1.3.0]: https://kdkhost.com.br/
[1.2.0]: https://kdkhost.com.br/
[1.1.0]: https://kdkhost.com.br/
[1.0.0]: https://kdkhost.com.br/
