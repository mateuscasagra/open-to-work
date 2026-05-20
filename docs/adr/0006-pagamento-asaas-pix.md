# 6. Pagamento via Asaas PIX (plano Pro)

Data: 2026-05-20
Status: Aceita

## Contexto

Lançamento do plano Pro (R$ 25/mês, candidaturas ilimitadas) precisa de um provedor de pagamento recorrente. Público é exclusivamente BR. Sem time financeiro, sem PCI-DSS in-house, VPS Hostinger pequena (4 GB RAM).

## Decisão

**Asaas Subscription com `billingType: PIX`**. Cobrança mensal automática gerando um novo PIX QR code a cada ciclo. Ativação Pro só acontece via webhook `PAYMENT_CONFIRMED` (não-otimista). Cancelamento mantém Pro até `current_period_end` — fica free por scheduler diário (`subscriptions:downgrade-expired`).

## Alternativas consideradas

- **Stripe:** padrão internacional, ótima DX. Caro em BR (4,99% + R$ 0,39 por transação cartão / sem PIX nativo robusto na época), forte em USD. Overkill pra Pro local R$ 25.
- **Mercado Pago / Pagar.me:** boas no Brasil. Mercado Pago tem PIX recorrente, Pagar.me tem subscriptions sólidas. Asaas ganhou por: API mais limpa, sandbox público sem aprovação, taxa PIX zero pra recebedor PJ pequeno, webhook simples com header-token (não exige HMAC complexo).
- **PIX recorrente nativo (BC):** standard novo do Banco Central, exige autorização explícita do usuário no app do banco. Adoção ainda baixa em 2026. Adicionar depois se necessário.
- **Cobranças avulsas mensais:** usuário pagaria manualmente todo mês. Alta evasão. Descartado.
- **Cartão de crédito:** maior conversão recorrente mas exige token/PCI no front (mesmo com Asaas hospedando, é mais friction no checkout BR). Pode entrar como segunda opção depois.

## Consequências

- **+** Sandbox Asaas pública sem aprovação — dev sem fricção
- **+** Header-token authentication no webhook é trivial (`hash_equals`), sem HMAC SHA-256 com timestamp
- **+** PIX é instant settlement no Brasil — confirmação em segundos, UX boa pro upgrade flow
- **+** Taxa PIX < cartão pra recebedor (mantém margem em R$ 25/mês)
- **+** Asaas Subscription gera payment automaticamente todo ciclo — sem cron próprio pra gerar cobranças
- **−** PIX QR expira (~3 dias). Se usuário deixa o modal aberto e volta dias depois, precisa gerar novo PIX (botão "gerar novo" pendente)
- **−** Sem cartão = sem cobrança 100% automática no sentido tradicional (usuário precisa entrar no banco todo mês). Mitigado: Asaas envia e-mail com novo PIX automaticamente
- **−** Vendor lock-in moderado. `AsaasGateway` interface mascara: trocar provedor exige reescrever 1 arquivo (`AsaasHttpClient`) + atualizar config + ajustar mapeamento de eventos do webhook
- **−** Sem suporte internacional. Aceito enquanto público é BR

## Pontos críticos da implementação

- **Ativação não-otimista.** `SubscribeToPro` salva `asaas_subscription_id` no DB mas **não** vira Pro até webhook `PAYMENT_CONFIRMED`. Evita "Pro grátis" se usuário fechar o modal sem pagar.
- **Idempotência forte no webhook.** `webhook_logs.event_id` é PRIMARY KEY com `insertOrIgnore` — atomic, sem race entre instâncias.
- **Cancel mantém Pro até `current_period_end`.** Padrão SaaS. Scheduler diário `subscriptions:downgrade-expired` faz o downgrade.
- **`asaas_customer_id` é preservado em downgrade.** Re-assinatura reusa o customer existente — não cria duplicado no painel Asaas.
- **Header validation timing-safe.** `hash_equals($expected, $provided)` em vez de `===`.
- **Processamento síncrono em fase 1.** Volume previsto < 100 webhooks/dia. Se crescer, trocar `$action->execute($payload)` por `dispatch(new ProcessAsaasWebhookJob($payload))` — interface preservada.
