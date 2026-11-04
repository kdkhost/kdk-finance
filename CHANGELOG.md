# Changelog

Todas as mudanças relevantes deste projeto são registradas neste arquivo.

O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o
versionamento segue o [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [1.8.3] - 2026-11-04

### Corrigido

- **API monetária em falha por cota**: a AwesomeAPI passou a responder `HTTP 429 (QuotaExceeded)` para o IP da hospedagem, fazendo as cotações caírem para o último valor conhecido em todas as telas. O serviço agora consulta provedores em cadeia — AwesomeAPI, `open.er-api.com` e Frankfurter — e usa o primeiro que responder com valores válidos.
- Pares livres (EUR-USD, USD-EUR etc.) continuam tentando o par direto da AwesomeAPI e, quando ela falha, são derivados pelo cruzamento BRL a partir das cotações do provedor alternativo.
- O resultado degradado (última cotação conhecida) agora fica em cache por 2 minutos, evitando repetir a consulta e prolongar o bloqueio a cada carregamento de página.

### Adicionado

- Configuração `finance.exchange.providers`, `er_api_url`, `frankfurter_url` e `failure_cache_minutes` para controlar a ordem e os endereços dos provedores sem alterar código.
- Testes de fallback: cotações vindas do provedor alternativo, par derivado por cruzamento quando a AwesomeAPI está limitada e cache do resultado degradado.

### Alterado

- Versão exibida no rodapé, menu lateral, login, páginas de erro, README e instalador atualizada para 1.8.3.

### Testado

- Suíte completa em PHP 8.3 com SQLite em memória: 121 testes e 559 asserções.
- Validação em produção: com a AwesomeAPI em `429`, as cotações retornam `USD 5,4861` e `EUR 6,1405` com origem `open.er-api`, e o par EUR-USD retorna `1,119283` por cruzamento BRL, sem marcação de cotação antiga.

## [1.8.2] - 2026-11-04

### Alterado

- Assinatura do desenvolvedor padronizada como **Marcelo Brad - RJ** em todo o sistema: cabeçalhos dos arquivos, metadados HTML, rodapé, menu lateral, login, páginas de erro, notificações, instalador, documentação e metadados do Composer.
- A identidade técnica fica centralizada em `config/finance.php`; os cabeçalhos `X-Developer-Name` e `X-System-Author` passam a ler essa configuração em vez de repetir o nome no middleware.
- Versão exibida no rodapé, menu lateral, login, páginas de erro, README e instalador atualizada para 1.8.2.

### Testado

- Suíte completa em PHP 8.3 com SQLite em memória e verificação de que nenhuma ocorrência do nome anterior permanece no código.

## [1.8.1] - 2026-11-04

### Adicionado

- **URLs sem `/public` em qualquer servidor**: endereços com o prefixo `/public/` respondem com redirecionamento permanente (`301`) para o caminho limpo, preservando o restante do caminho e a query string. A regra existe no `.htaccess` da raiz, no `.htaccess` de `public/` e em um middleware global que cobre NGINX, proxy reverso e o servidor embutido do PHP.
- Testes de URL limpa: `/public/login` → `/login`, diretório `/public` → raiz, query string preservada, requisições `POST`, cabeçalhos de segurança no redirecionamento e garantia de que nenhuma URL gerada contém `/public/`.

### Alterado

- O instalador remove `/public` do endereço informado antes de gravar o `APP_URL`. Se uma instalação antiga tiver o caminho no `APP_URL`, ele é normalizado em tempo de execução, sem alterar o `.env`.
- Documentação do cPanel explica o redirecionamento nos dois modos de Document Root, e o checklist inclui o teste do endereço limpo.
- Versão exibida no rodapé, menu lateral, login, páginas de erro, README e instalador atualizada para 1.8.1.

### Testado

- Suíte completa em PHP 8.3 com SQLite em memória: 118 testes e 549 asserções, incluindo os novos testes de URL limpa.
- Validação em produção por `curl`: `/public/login` responde `301` para `/login`, `/login` responde `200` e a query string é mantida.

### Produção

- Instalação de `financeiro.kdkhost.com.br` recebeu a configuração de LicenseBoy e OVH validada localmente: credenciais criptografadas em `app_settings` (7 chaves da OVH e 3 da LicenseBoy), 37 itens de catálogo, 5 licenças vinculadas ao painel com preço sincronizado, 6 serviços OVH, 5 blocos de IP, 17 faturas e 121 itens.
- Parâmetros financeiros alinhados ao ambiente local: IOF de 3,50% em impostos e conversão e spread de 2%.
- Scripts temporários de importação e o arquivo de carga usado na migração removidos do servidor após a validação.

## [1.8.0] - 2026-11-04

### Adicionado

- **Segurança operacional controlada 100% pelo painel**: nova aba *Segurança* em Configurações grava tudo na tabela `app_settings` (grupo `security`), sem `.env` e sem reiniciar o PHP. Cobre HTTPS forçado, HSTS com validade, cabeçalhos de segurança, Content Security Policy, opções de frame, `no-store`, restrição de hosts (com curingas) e proxies confiáveis.
- **Sessão e login configuráveis no painel**: duração da sessão, SameSite, cookie seguro, sessão criptografada, expiração ao fechar o navegador, limite de tentativas de login e janela de bloqueio. O bloqueio passa a usar exatamente os valores salvos, com registro em log.
- **Política de senhas configurável**: tamanho mínimo, maiúsculas e minúsculas, números e símbolos, aplicada em criação, edição e troca da própria senha.
- **Revogação de outras sessões** pela aba Segurança, listando dispositivo, IP, último acesso e a sessão atual.
- **SMTP completo no painel**: ativar/desativar envio, servidor, porta, criptografia (TLS, SSL ou nenhuma), autenticação, usuário, senha criptografada, tempo limite, remetente, nome, responder para e envio de e-mail de teste. A configuração é aplicada em tempo de execução, sem editar o `.env`.
- **Tema de e-mail responsivo e editável**: marca, selo com iniciais, cinco cores, rodapé, assinatura, e-mail e site de suporte e dados da empresa, com prévia ao vivo em iframe. Um layout compartilhado (`emails.themed`) e o mailable `ThemedMail` alimentam os envios reais.
- **Notificação de variação cambial** passou a usar o tema configurado, com tabela de detalhes, botão de ação e pré-cabeçalho.
- Novo grupo de configurações com cache por grupo em `AppSetting::group()`, descriptografando segredos na leitura e invalidando o cache do grupo ao gravar.
- Página de erro 400 em português para requisições com host não permitido.
- Testes do painel de configurações: cache por grupo, abas, senha preservada em branco, SMTP em tempo de execução, envio de teste, prévia, persistência, CSP e cabeçalhos, HSTS só em HTTPS, restrição de hosts, bloqueio de login, política de senha, revogação de sessões, permissão de desenvolvedor e notificação temática.

### Alterado

- Cabeçalhos de segurança, HTTPS e restrição de host deixaram de ler `FORCE_HTTPS` e `TRUST_HOSTS` do `.env` e passaram a ler do banco, com valores padrão seguros quando ainda não há registro.
- O instalador grava as preferências de segurança e de SMTP em `app_settings` (senha criptografada) em vez de espalhar credenciais pelo `.env`; o `.env` fica apenas com `MAIL_MAILER=log` e o remetente.
- Sem SMTP ativo, o envio cai para o log em vez de falhar; o diagnóstico na aba E-mail mostra driver, situação, servidor, remetente e último teste.
- Versão exibida no rodapé, menu lateral, login, páginas de erro, README e instalador atualizada para 1.8.0.

### Testado

- Suíte completa com 111 testes e 527 asserções passando.
- Abas E-mail e Segurança validadas no navegador: prévia responsiva carregando o tema salvo, envio de teste bloqueado enquanto o SMTP não está configurado, sessões ativas, gravação com Toastr e ausência de rolagem horizontal.

## [1.7.0] - 2026-11-04

### Adicionado

- **Instalador web para hospedagem compartilhada (cPanel)** em `public/instalador.php`, em português, com verificação de requisitos do PHP, criação do `.env`, migrações, configurações padrão, administrador e usuário de leitura na mesma tela.
- Proteções do instalador: senha de instalação em `storage/app/instalar.senha`, token CSRF, campo-isca contra robôs, bloqueio de uma hora após dez senhas erradas, trava em `storage/app/installed.lock` e renomeação automática para `instalador.php.instalado` ao concluir. A senha e o contador são apagados no sucesso.
- **Empacotador para cPanel** (`php tools/empacotar-cpanel.php`) que gera `dist/kdk-finance-cpanel-<versão>.zip` com `vendor/`, build do Vite, instalador e `.htaccess`, criando uma senha de instalação aleatória dentro do pacote. Ficam de fora `.env`, bancos SQLite, logs, testes, credenciais locais e `node_modules`.
- **Guia de instalação em cPanel** em `docs/INSTALACAO-CPANEL.md` e PDF com a identidade visual do sistema, gerado por `php tools/gerar-pdf.php`. Cobre requisitos, envio, Document Root, banco, instalador, cron, SSL, backup, atualização, perfil somente leitura e solução de problemas.
- Cabeçalhos de segurança em todas as respostas: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, `Cross-Origin-Resource-Policy` e `Cache-Control: no-store` para usuários autenticados.
- **Content Security Policy com nonce por requisição**, aplicada a todos os scripts inline do painel, login, erros e telas de desenvolvimento.
- Redirecionamento para HTTPS (`FORCE_HTTPS`) e restrição de Host header (`TRUST_HOSTS`) configuráveis, com suporte a proxies confiáveis.
- Revogação imediata da sessão de usuários desativados, com mensagem em português.
- Política de senha única de 10+ caracteres com maiúscula, minúscula e número para criação, edição e troca da própria senha.
- `.htaccess` na raiz do projeto, para quando o Document Root não aponta para `public/`, bloqueando `.env`, `.git`, `vendor/`, `storage/`, `database/`, `config/`, `tests/` e arquivos de projeto.
- Cabeçalhos de segurança e bloqueio de arquivos ocultos também no `public/.htaccess`.
- Testes de segurança: cabeçalhos, nonce do CSP, revogação de sessão, matriz de leitura e escrita do perfil Consulta, política de senha, além de testes do gerador de `.env`.

### Alterado

- Consultas MySQL/MariaDB usam *prepared statements* reais (`PDO::ATTR_EMULATE_PREPARES = false`).
- `APP_DEBUG` é forçado a `false` em produção mesmo que o `.env` esteja incorreto, com registro em log.
- Notificações Toastr escapam HTML; tentativas de login inválidas e bloqueios são registrados em log.
- Dicas de senha das telas de login, perfil e usuários atualizadas para a nova política.
- O Echo só inicializa quando o servidor realmente usa Reverb; em hospedagem compartilhada o painel segue atualizando por consulta a cada 60 segundos, sem erros de WebSocket no console.
- `.env.example` documenta sessão criptografada, cookie seguro, HTTPS forçado e hosts confiáveis para produção.
- Versão exibida no rodapé, menu lateral, login e páginas de erro atualizada para 1.7.0.

### Corrigido

- Leitura de valores escapados no `.env` gerado pelo instalador (barras, aspas e cifrões), mantendo a chave da aplicação e senhas intactas.

### Testado

- Suíte completa com 94 testes e 437 asserções passando, incluindo o novo instalador, os cabeçalhos de segurança, o perfil somente leitura e o empacotamento para cPanel.

## [1.6.0] - 2026-11-04

### Adicionado

- **Atualização em segundo plano em todas as páginas do painel**, sem recarregar a página e sem piscar nenhum elemento: um instantâneo leve (`/painel/ao-vivo`) alimenta notificações não lidas, totais do dashboard, cotações, totais das faturas listadas e a cotação da tela de conversões a cada 60 segundos, além de uma consulta imediata ao voltar para a aba.
- Os valores só são escritos quando realmente mudam, preservando ícones, classes e o restante do conteúdo de cada célula — a tela permanece estável durante a atualização.
- **Relógio em tempo real** no cabeçalho com data e hora de **São Paulo** (`America/Sao_Paulo`), atualizado a cada segundo e sincronizado pelo horário do servidor.
- Indicador do cabeçalho mostra o horário da última atualização recebida.

### Alterado

- `APP_TIMEZONE` documentado e fixado em `America/Sao_Paulo` como padrão do sistema, tanto no `config/app.php` quanto no `.env.example`.
- O tema claro/escuro salvo no navegador passou a ser aplicado **antes** do CSS carregar, em todas as telas (painel, login e páginas de erro), eliminando a piscada clara ao abrir uma página no tema escuro.
- Removidos a animação pulsante do indicador e o realce temporário das linhas de fatura, que causavam movimento desnecessário na tela.

### Testado

- 80 testes e 357 asserções passando, incluindo o novo instantâneo, a exigência de autenticação, o fuso padrão e a presença do relógio e do endereço de atualização no HTML do painel.

## [1.5.4] - 2026-11-04

### Corrigido

- Menu lateral marcava **Conversões** e **Nova conversão** ao mesmo tempo nas telas de cadastro e edição. O item Conversões agora só fica ativo na listagem e no detalhe; o item Nova conversão fica ativo apenas na própria tela de cadastro, como já acontece com Faturas e Nova fatura.

## [1.5.3] - 2026-11-04

### Alterado

- **Página de conversões no padrão das demais telas**: cabeçalho, cartões de resumo, cartão de filtros e tabela, com os mesmos componentes usados em Faturas e Licenças.
- Cartões de resumo com a cotação EUR → USD agora (taxa inversa, horário e fonte), conversões salvas, custo médio conferido e equivalente em reais — exibidos apenas quando há registros.
- Filtros reorganizados no formato de Faturas (busca, direção, “só conferidas”, botões) com atalhos em etiquetas para todas, conferidas e as tarifas padrão de spread, IOF e margem.
- Botão **Tarifas** no cabeçalho, levando direto à aba de conversão das configurações.
- Estado vazio padrão (`.empty-state`) com a cotação atual e uma única chamada para criar a primeira conversão; sem cartões zerados e sem tabela em branco.
- Removidos os estilos exclusivos da tentativa anterior de layout, deixando a página apenas com os componentes compartilhados do sistema.
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
