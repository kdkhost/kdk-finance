# Impostos e tarifas em pagamentos internacionais

Este documento explica o que o sistema já cobrava, o que faltava, o que é
obrigatório no pagamento brasileiro de serviços e produtos vindos da Europa e
como as tarifas são atualizadas automaticamente.

Todas as alíquotas ficam em **Configurações → Câmbio e tributos**, gravadas em
`app_settings`. Nenhuma delas é lida do `.env` em produção.

## As duas camadas

A fatura internacional tem dois custos diferentes, e o sistema passou a
discriminá-los separadamente:

1. **Encargos do cartão ou do banco** — o que aparece na fatura do cartão:
   spread cambial, IOF e tarifa fixa contratada.
2. **Tributos da importação** — o que a empresa recolhe por conta própria ao
   importar o serviço ou o produto. Não são cobrados pelo banco.

O **total a reservar** é a soma das duas camadas. Antes, a tela misturava tudo
num único total e não deixava claro o que era tarifa e o que era tributo.

## O que já existia

- Spread cambial (padrão 2%) e IOF (3,5%) sobre a fatura do cartão.
- ICMS e "outros tributos" como campos genéricos, sem separar serviço de
  produto e sem base legal documentada.
- Cálculo do total em reais a partir da cotação do dia.

## O que faltava e agora existe

| Tributo / tarifa | Quando incide | Alíquota padrão | Base legal |
|---|---|---|---|
| PIS/COFINS-Importação | Serviços importados | 9,25% (1,65% + 7,6%) | Lei 10.865/2004 |
| IRRF | Rendimento pago no exterior | 15% (25% em paraíso fiscal) | Lei 9.779/1999, art. 7º |
| ISS | Serviço importado | 5% (2% a 5% por município) | LC 116/2003 |
| CIDE | Royalties, serviços técnicos e transferência de tecnologia | 10% | Lei 10.168/2000 |
| CBS (fase de teste 2026) | Serviços, se a contabilidade destacar | 0,9% | LC 214/2025 |
| IBS (fase de teste 2026) | Serviços, se a contabilidade destacar | 0,1% | LC 214/2025 |
| ICMS de importação | Produtos físicos — 17% a 20% por estado | 17% | Convênio 81/2023 |
| Imposto de Importação | Produtos acima de US$ 50, com dedução de US$ 30 no Remessa Conforme | 60% | Regulamento Aduaneiro |
| Tarifa fixa do cartão/banco | Por operação | Manual | Contrato com o banco |
| IOF | Cartão internacional e remessa | 3,5% desde 11/06/2025 | Decreto 6.306/2007, art. 15-B, VII, redação do Decreto 12.499/2025 |

### O que é obrigatório e o que é condicional

- **Sempre configurados para serviços**: PIS/COFINS-Importação, IRRF e ISS.
  São os tributos que a empresa recolhe ao pagar fornecedor estrangeiro.
- **Condicionais**: CIDE (só royalties/serviços técnicos), CBS e IBS (fase de
  teste de 2026, dependem da orientação contábil), ICMS e Imposto de
  Importação (somente produtos físicos).
- **Tarifas, não tributos**: spread e tarifa fixa são custo do banco/bandeira.
  O IOF é tributo federal cobrado na operação do cartão ou remessa.

## Fórmula

```
base            = valor original × cotação
spread          = base × spread%
subtotal cartão = base + spread + tarifa fixa
IOF             = subtotal cartão × IOF%
total cartão    = subtotal cartão + IOF

base do tributo = base + spread  (sem a tarifa fixa do banco)
tributo         = base do tributo × alíquota
total a reservar = total cartão + tributos
```

O Imposto de Importação usa ainda a dedução em dólar convertida pela cotação
do dia, aplicada apenas quando o valor original passa de US$ 50.

Cada fatura guarda a **natureza** (`Serviço` ou `Produto`) e o detalhamento em
`tax_breakdown`, para que a tela, o e-mail e o CSV mostrem exatamente as
alíquotas usadas no cálculo — inclusive depois de uma mudança de configuração.

## Atualização automática das tarifas

O comando `finance:sync-fees` roda diariamente às 6h20 (America/Sao_Paulo) pela
Central de cron e também pode ser disparado em **Configurações → Câmbio e
tributos → Sincronizar**.

| Fonte | O que é lido | Limite conhecido |
|---|---|---|
| Visa | Markup cambial público do simulador da bandeira | Endpoint público, sem chave |
| Mastercard | Taxa de conversão; o markup é derivado da cotação de mercado | O endpoint pode responder 403 (proteção Akamai); a falha é registrada e não derruba o cron |
| Banco Central (PTAX) | Dólar e euro de fechamento (Olinda) | Sem cotação em fins de semana/feriados; o serviço retrocede até 7 dias |
| PayPal | Spread efetivo estimado pela mediana das conversões já registradas (`provider_rate` × `market_rate`) | O PayPal não publica taxa por API; a leitura depende de haver conversões registradas |
| Bancos | Referência oficial é a PTAX; tarifa fixa continua manual | Bancos não publicam API de tarifas |

Por segurança, a leitura automática **não altera** o spread da fatura sozinha.
O administrador liga a chave **Atualizar spread automaticamente** e escolhe a
fonte (`manual`, `visa` ou `paypal`). Sem esse opt-in, o cron apenas registra a
referência e o histórico em `fee_sync_runs`.

## Como configurar

1. Abra **Configurações → Câmbio e tributos**.
2. Revise as alíquotas de serviços com a contabilidade (ISS do município,
   acordos de bitributação para IRRF, CIDE quando houver serviço técnico).
3. Ligue os tributos que se aplicam ao seu regime; ICMS e Imposto de
   Importação ficam desligados para faturas de serviço.
4. Se quiser o spread automático, ligue a chave e escolha a fonte.
5. Clique em **Sincronizar** para ler as referências na hora; o cron mantém a
   leitura diária.
6. Ao mudar alíquotas, use **Recalcular** nas faturas pendentes e destravadas
   para aplicar os novos valores.

## Testes

A suíte cobre o cálculo das duas camadas, a troca entre serviço e produto, a
dedução do Imposto de Importação, a gravação das alíquotas no painel e a
sincronização de Visa, Mastercard, PTAX e PayPal com respostas simuladas —
inclusive o caso de bloqueio da Mastercard, que precisa continuar registrando
a falha sem interromper o cron.
