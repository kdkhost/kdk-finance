# Instalação no cPanel

KDK Finance — Sistema Financeiro Multimoedas & Gestão de Infraestrutura
KDKHost Soluções · CNPJ 33.986.975/0001-90 · contato@kdkhost.com.br

Este guia cobre a instalação em **hospedagem compartilhada com cPanel**, desde o
envio do pacote até o primeiro acesso, incluindo cron, SSL, permissões e a
conta de **somente leitura**.

---

## 1. O que a hospedagem precisa ter

| Item | Mínimo | Observação |
| --- | --- | --- |
| PHP | 8.2 ou superior | 8.3 é o ambiente oficial de desenvolvimento |
| Banco | MySQL 5.7+/MariaDB 10.3+ | SQLite também funciona, mas o MySQL é recomendado em cPanel |
| Extensões PHP | openssl, pdo, pdo_mysql, mbstring, fileinfo, curl, tokenizer, xml, ctype, json, dom, filter, hash, session | O instalador confere todas antes de gravar qualquer arquivo |
| Espaço em disco | 350 MB | O pacote leva o `vendor/` e o build do front-end |
| HTTPS | obrigatório | Use o AutoSSL/Let's Encrypt do cPanel antes de liberar o acesso |

O pacote **não precisa de Composer nem de Node** no servidor. Tudo já vai
compilado.

---

## 2. Gerar o pacote

No ambiente de desenvolvimento:

```bash
composer install
npm run build
php tools/empacotar-cpanel.php
```

O comando cria:

```
dist/kdk-finance-cpanel-<versão>.zip
```

O ZIP contém a aplicação, o `vendor/`, o `public/build/`, o instalador e os
arquivos de proteção `.htaccess`. Ficam de fora: `.env`, bancos SQLite, logs,
`node_modules`, testes, credenciais locais e a pasta `dist`.

Ao final o comando imprime a **senha de instalação** e o SHA-256 do arquivo. A
mesma senha fica em `storage/app/instalar.senha` dentro do ZIP. Guarde-a: ela
será pedida pelo instalador e apagada ao final.

> **Nunca** versione essa senha. Ela vale apenas para a instalação daquele
> pacote e deixa de existir depois da conclusão.

---

## 3. Enviar os arquivos

### 3.1. Criar a pasta da aplicação

No Gerenciador de Arquivos do cPanel, crie uma pasta **fora de `public_html`**:

```
/home/SEU_USUARIO/financeiro
```

Envie o conteúdo do ZIP para essa pasta (e extraia ali). A estrutura deve
ficar assim:

```
financeiro/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/
│   ├── build/
│   ├── instalador.php
│   └── index.php
├── resources/
├── routes/
├── storage/
├── vendor/
├── .htaccess
├── artisan
└── composer.json
```

### 3.2. Apontar o domínio para `public/`

No cPanel, abra **Domínios** (ou *Subdomínios*) e altere o *Document Root* do
domínio/subdomínio para:

```
/home/SEU_USUARIO/financeiro/public
```

Essa é a forma mais segura: o `.env`, o `vendor/` e o banco SQLite ficam
inacessíveis pela web.

**Alternativa sem mudar o Document Root:** envie o conteúdo para
`public_html/financeiro/`. O `.htaccess` da raiz reescreve as requisições para
`public/` e bloqueia `.env`, `.git`, `vendor/`, `storage/` e demais pastas
internas. Ainda assim, apontar o Document Root para `public/` é preferível.

Em qualquer um dos dois modos a pasta `public` **não aparece na URL**. O
`.htaccess` responde `301` para `/public/login`, `/public/build/...` e qualquer
outro endereço com o prefixo, entregando o caminho limpo (`/login`,
`/build/...`). O mesmo vale para NGINX ou proxy reverso, graças a um middleware
da aplicação; a query string é preservada.

### 3.3. Permissões

No Gerenciador de Arquivos:

| Caminho | Permissão |
| --- | --- |
| Pastas em geral | 755 |
| `storage/` e subpastas | 775 |
| `bootstrap/cache/` | 775 |
| `.env` (criado pelo instalador) | 640 |

Se o PHP rodar com um usuário diferente do seu, use 775 nas pastas graváveis.
Evite 777.

---

## 4. Criar o banco de dados

No cPanel, em **Bancos de Dados MySQL**:

1. Crie um banco, por exemplo `usuario_financeiro`.
2. Crie um usuário com senha forte, por exemplo `usuario_finance`.
3. Adicione o usuário ao banco com **todos os privilégios**.
4. Anote os três dados: nome do banco, usuário e senha. O servidor costuma ser
   `localhost` e a porta `3306`.

O banco precisa estar **vazio**. Se já existir uma tabela `migrations`, o
instalador se recusa a continuar para não sobrescrever dados.

---

## 5. Rodar o instalador

Acesse:

```
https://financeiro.seudominio.com/instalador.php
```

O instalador é uma página única, em português, com estas proteções:

- só funciona enquanto não existir `storage/app/installed.lock` nem `APP_KEY`
  no `.env`;
- exige a senha de instalação gravada em `storage/app/instalar.senha`;
- valida token CSRF em todos os envios;
- usa campo-isca invisível contra robôs;
- bloqueia por 1 hora após 10 senhas de instalação erradas;
- ao concluir, apaga a senha, o contador de tentativas e **renomeia a si mesmo**
  para `instalador.php.instalado`.

### 5.1. Preencha

1. **Senha de instalação** — a que o empacotador imprimiu.
2. **Endereço do sistema** — `https://financeiro.seudominio.com` (com HTTPS).
3. **Banco de dados** — MySQL/MariaDB e os dados do passo 4. SQLite é aceito
   para testes, mas não é recomendado em produção.
4. **Administrador** — nome, e-mail e senha. A senha precisa ter **10+
   caracteres, maiúscula, minúscula e número**.
5. **Acesso somente leitura** (opcional, marcado por padrão) — cria na hora um
   usuário com perfil *Consulta*, que só visualiza.
6. **SMTP** (opcional) — host, porta, criptografia, usuário, senha e remetente.
   Sem SMTP, os e-mails ficam registrados no log e o painel continua
   funcionando.

### 5.2. O que o instalador faz

- grava o `.env` com `APP_ENV=production`, `APP_DEBUG=false`, chave aleatória,
  cookies de sessão criptografados e `HttpOnly`, `SameSite=Lax`;
- usa `SESSION_DRIVER=database`, `CACHE_STORE=database` e
  `QUEUE_CONNECTION=database` (não depende de Redis);
- define `BROADCAST_CONNECTION=log`, pois a hospedagem compartilhada não mantém
  o Reverb rodando — o painel se atualiza por consulta a cada 60 segundos;
- roda as migrações e semeia as configurações padrão;
- cria o administrador e, se marcado, o usuário somente leitura;
- grava `storage/app/installed.lock` com data, versão, URL e nomes das contas;
- apaga `storage/app/instalar.senha`, os contadores de tentativa e o cache de
  configuração;
- renomeia `public/instalador.php` para `public/instalador.php.instalado`.

Ao final, aparece o resumo com o endereço de login. A senha do administrador
**não** é exibida nem gravada em lugar nenhum além do hash no banco.

---

## 6. Depois da instalação

### 6.1. Conferir o bloqueio do instalador

Acesse novamente `/instalador.php`. Deve aparecer “Instalação já concluída” (o
arquivo renomeado continua respondendo apenas para informar isso; a página não
aceita mais envios). Se quiser removê-lo de vez:

```
public/instalador.php.instalado
```

### 6.2. Ativar HTTPS

1. No cPanel, instale o AutoSSL para o domínio.
2. Confirme que `https://financeiro.seudominio.com` abre sem aviso.
3. O instalador já gravou **Forçar HTTPS** e **Restringir hosts** no banco
   (`app_settings`) quando a URL informada era `https`. Confira em
   **Configurações → Segurança**, onde também ficam HSTS, cabeçalhos, CSP,
   sessão, bloqueio de login e política de senhas. Nada disso vem do `.env`.

### 6.3. Cron do Laravel

Em **Trabalhos Cron**, crie duas tarefas de **1 em 1 minuto**:

```bash
/usr/local/bin/ea-php82 /home/SEU_USUARIO/financeiro/artisan schedule:run >> /dev/null 2>&1
```

```bash
/usr/local/bin/ea-php82 /home/SEU_USUARIO/financeiro/artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

Ajuste o caminho do PHP se o cPanel informar outro (alguns servidores usam
`/usr/bin/php`, `/opt/cpanel/ea-php82/root/usr/bin/php` ou
`/opt/alt/php82/usr/bin/php`). Use sempre o CLI 8.2 ou superior: em LiteSpeed o
`php` do terminal pode ser uma versão antiga. O `schedule:run` dispara o
`cron:run-due`, que executa as tarefas cadastradas na Central de cron; o
`queue:work` processa e-mails e notificações da fila no banco.

> As tarefas cadastradas **dentro do painel** (Central de cron) são executadas
> pelo `schedule:run`. Não é preciso criar um cron no cPanel para cada uma.

### 6.3.1. Crons do sistema

A instalação já cria cinco agendamentos na **Central de cron**:

| Rotina | Comando | Frequência |
| --- | --- | --- |
| Atualizar cotações de câmbio | `finance:refresh-rates` | a cada 30 minutos |
| Sincronizar faturas OVHcloud | `finance:sync-invoices` | a cada 15 minutos |
| Sincronizar preços das licenças | `licenses:sync-prices` | diariamente, 05:10 |
| Sincronizar painel LicenseBoy | `licenses:sync-panel` | diariamente, 05:25 |
| Alertas de vencimento | `finance:notify-alerts` | diariamente, 08:00 |

Fuso de São Paulo em todos. Se a lista estiver vazia (instalação antiga ou
registros removidos), use **Restaurar padrões** no topo da Central de cron: o
botão cria os que faltam e realinha os existentes sem duplicar.

O agendador é considerado ativo quando o selo **Agendador · Ativo** aparece no
topo da Central de cron; ele é atualizado a cada minuto pelo `schedule:run`.

O cartão **Ambiente de execução** mostra e permite fixar o binário do PHP CLI
usado pelos agendamentos (`app_settings.cron_php_binary`). Em branco, o sistema
detecta automaticamente um PHP 8.2+ (`/usr/local/bin/ea-php82`,
`/opt/cpanel/ea-php82/root/usr/bin/php`, `/opt/alt/php82/usr/bin/php`,
`/usr/bin/php8.2` ou o `PHP_BINDIR`). A linha única de scheduler exibida no
painel já sai com o caminho absoluto detectado.

### 6.3.2. Modelos de e-mail

Em **Configurações → Modelos de e-mail** (ou pelo menu lateral **Modelos de
e-mail**) ficam os oito textos transacionais do sistema. Cada modelo tem prévia,
envio de teste, ativação individual e **Restaurar padrão**. Os destinatários dos
alertas são definidos em **Configurações → E-mail → Alertas automáticos**; sem
endereço preenchido, o sistema usa o e-mail de todo desenvolvedor e gestor
financeiro ativo.

### 6.4. Backup

Guarde, no mínimo:

- o banco de dados (backup nativo do cPanel ou `mysqldump`);
- a pasta `storage/app/` (anexos e arquivos privados);
- o arquivo `.env` (contém a `APP_KEY`; sem ela, dados criptografados como as
  credenciais OVH/LicenseBoy não podem ser lidos).

O `.env` não entra no pacote justamente por conter segredos. Faça uma cópia
segura fora do servidor.

### 6.5. Atualizar o sistema

1. Gere um novo pacote com `php tools/empacotar-cpanel.php`.
2. Envie por cima de `app/`, `bootstrap/`, `config/`, `database/`, `public/`,
   `resources/`, `routes/`, `vendor/`, `artisan`, `composer.json` e `.htaccess`.
3. **Preserve** o `.env` e a pasta `storage/`.
4. Rode as migrações:

```bash
/usr/local/bin/php /home/SEU_USUARIO/financeiro/artisan migrate --force
```

5. Limpe os caches:

```bash
/usr/local/bin/php /home/SEU_USUARIO/financeiro/artisan optimize:clear
```

---

## 7. Perfil somente leitura

O sistema tem três perfis:

| Perfil | Pode ver | Pode alterar |
| --- | --- | --- |
| Desenvolvedor / Administrador | tudo | tudo, inclusive usuários, configurações e central de cron |
| Gestor financeiro | tudo | faturas, licenças, conversões e tributos/tarifas |
| Consulta (somente leitura) | painel, faturas, serviços, licenças, conversões, notificações e próprio perfil | nada |

O usuário *Consulta*:

- acessa `/painel`, `/faturas`, `/servicos`, `/licencas`, `/conversoes`,
  `/notificacoes` e `/perfil`;
- recebe **403** em `/configuracoes`, `/usuarios`, `/crons`, telas de cadastro,
  exportações e qualquer envio de formulário;
- pode trocar a própria senha em **Meu perfil**;
- tem a sessão encerrada automaticamente se for desativado por um
  administrador.

Para criar outro usuário de leitura depois: entre como Desenvolvedor, abra
**Usuários**, escolha o perfil *Consulta (somente leitura)* e defina uma senha
com 10+ caracteres, maiúscula, minúscula e número.

---

## 8. Checklist de segurança

- [ ] Document Root aponta para `public/` (ou `.htaccess` da raiz ativo).
- [ ] `https://financeiro.seudominio.com/public/login` redireciona (`301`)
      para `https://financeiro.seudominio.com/login`.
- [ ] `https://` funcionando e **Forçar HTTPS** ativo em
      Configurações → Segurança.
- [ ] `APP_DEBUG=false` e `APP_ENV=production`.
- [ ] `instalador.php` renomeado; `storage/app/instalar.senha` apagada.
- [ ] `.env` com permissão 640 e fora do alcance da web.
- [ ] `storage/` e `bootstrap/cache/` graváveis apenas pelo necessário.
- [ ] Cron de `schedule:run` e `queue:work` ativos.
- [ ] Central de cron com os cinco crons do sistema e o selo **Agendador · Ativo**.
- [ ] Modelos de e-mail revisados e um envio de teste recebido.
- [ ] Backup do banco, de `storage/app/` e do `.env`.
- [ ] Credenciais OVH/LicenseBoy cadastradas **pelo painel** (ficam
      criptografadas em `app_settings`, nunca no `.env`).
- [ ] SMTP configurado em **Configurações → E-mail**, com e-mail de teste
      recebido e tema dos e-mails revisado na prévia.
- [ ] Usuário somente leitura testado com `403` nas telas administrativas.

### O que já vem protegido

- cabeçalhos `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
  `Permissions-Policy`, `Cross-Origin-Opener-Policy` e
  `Cross-Origin-Resource-Policy`;
- Content Security Policy com *nonce* por requisição, sem `unsafe-inline` para
  scripts;
- consultas com *prepared statements* reais no MySQL (`PDO::ATTR_EMULATE_PREPARES
  = false`);
- saída escapada pelo Blade e `escapeHtml` nas notificações;
- senhas com hash bcrypt (12 rounds) e política de 10+ caracteres;
- bloqueio de login após 10 tentativas por minuto, com registro em log;
- sessão criptografada, `HttpOnly`, `SameSite=Lax` e revogação imediata de
  usuários desativados;
- `.htaccess` bloqueando `.env`, `.git`, `vendor/`, `storage/`, `database/`,
  `config/`, `tests/` e arquivos de projeto;
- `APP_DEBUG` desligado à força em produção, mesmo que o `.env` esteja errado;
- credenciais de API criptografadas com `Crypt::encryptString` no banco.

Nenhum sistema é imune a tudo. Essas camadas cobrem as falhas mais comuns de
aplicações Laravel em hospedagem compartilhada: injeção SQL, XSS, exposição de
arquivos sensíveis, sequestro de sessão, força bruta e vazamento de credenciais.

---

## 9. Problemas comuns

**Erro 500 logo após o envio**
Confira as permissões de `storage/` e `bootstrap/cache/` (775) e veja
`storage/logs/laravel.log`.

**“Instalação já concluída” e não consigo reinstalar**
É proposital. Para recomeçar do zero, remova `storage/app/installed.lock` e o
`.env` **somente** se puder perder a instalação atual; depois recrie
`storage/app/instalar.senha` com uma senha nova.

**“Senha de instalação incorreta”**
O arquivo `storage/app/instalar.senha` foi apagado ou o ZIP foi extraído sem
ele. Crie o arquivo com uma senha à sua escolha (sem espaços) e informe a mesma
senha no formulário. Após 10 erros, aguarde 1 hora ou apague
`storage/app/install-attempts.json`.

**Não conecta ao banco**
Confirme nome do banco, usuário, senha, host `localhost` e porta `3306`. No
cPanel, o usuário do banco costuma ter o prefixo da conta (ex.:
`usuario_finance`).

**Tela sem estilo ou 404 em `/build/...`**
O `public/build/` não foi enviado. Reenvie o pacote completo.

**Login volta para a tela de login**
Se estiver acessando por `http://`, o cookie seguro não é enviado. Use a URL
`https://` cadastrada no `.env` ou ajuste `APP_URL`.

**Cron não executa**
Use o caminho completo do PHP informado pelo cPanel e teste manualmente:

```bash
/usr/local/bin/php /home/SEU_USUARIO/financeiro/artisan schedule:run
```

**E-mails não saem**
Confira SMTP em **Configurações** no painel (o painel grava no banco; o `.env`
só é usado quando o banco ainda não tem a configuração). Veja também
`storage/logs/laravel.log`.

---

## 10. Suporte

KDKHost Soluções
E-mail: contato@kdkhost.com.br
Site: https://kdkhost.com.br

Desenvolvido por Marcelo Brad - RJ.
