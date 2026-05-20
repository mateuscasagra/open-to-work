# Módulo Support

**Propósito:** canal público de contato — visitante da landing (logado ou não) envia título + descrição via formulário e o sistema dispara e-mail pra `SUPPORT_EMAIL` configurado.

## Endpoints

| Método | Rota | Handler | Throttle / Auth |
|---|---|---|---|
| `POST` | `/api/support` | `SupportController@store` | **público** + `throttle:support` (5/hora por IP) |

**Body:**
```json
{
  "name": "Diego",            // opcional, max 120
  "email": "diego@x.com",     // obrigatório, RFC valid, max 255
  "title": "Título da mensagem",  // obrigatório, 3-200 chars
  "description": "Corpo..."   // obrigatório, 10-5000 chars
}
```

**Responses:**
- `200 {ok:true}` — sucesso (e-mail enviado)
- `422 {errors:{...}}` — validação
- `429` — rate-limit estourado
- `500 {message}` — falha de envio (SMTP fora etc) — logado em `error_logs`/Sentry

## Backend

**Controller:** `backend/app/Http/Controllers/Api/SupportController.php` — chama `Mail::to(config('services.support.recipient'))->send(new SupportRequestMail(...))`.

**FormRequest:** `backend/app/Http/Requests/Support/StoreSupportRequest.php` — `authorize()` retorna true (público), valida formato.

**Mailable:** `backend/app/Mail/SupportRequestMail.php` — Markdown, `replyTo` aponta pro e-mail do remetente (admin clica "Responder" no Gmail e vai direto pro user).

**Atenção:** propriedades chamadas `subjectLine` e `messageBody` em vez de `subject`/`body` — a classe pai `Mailable` já tem essas como `protected $subject` não-readonly; redeclarar como readonly via constructor promotion dá `Cannot redeclare`.

**Blade template:** `backend/resources/views/mail/support-request.blade.php` — `nl2br(e($messageBody))` preserva quebras de linha do textarea.

**Config:** `config/services.php` → `support.recipient` lê `SUPPORT_EMAIL` env (default `zandonacasagrande@gmail.com`).

**Rate limiter** em `AppServiceProvider::configureRateLimiters()`:
```php
RateLimiter::for('support', fn ($r) => Limit::perHour(5)->by('ip:' . $r->ip()));
```

**i18n:** `backend/lang/{pt_BR,en,es}/support.php` — `anonymous_name` (fallback quando user não preenche nome) + `errors.send_failed`.

## Frontend

**Arquivos:** `frontend/src/modules/support/`

- **`composables/useSupportRequest.ts`** — `useMutation`. Mapeia 422→`kind: 'validation'` (carrega mensagem do backend), 429→`'rate_limited'`, outros→`'unknown'`.
- **`views/SupportView.vue`** — rota pública `/support`. Card centrado padrão `ForgotPasswordView`/`LoginView`. Form com nome (opcional), e-mail (obrigatório), assunto, descrição (textarea 7 rows + contador `X/5000`). Pré-preenche nome/e-mail se o usuário estiver logado (via `auth.user`). Tela de sucesso com checkmark verde após envio.

**Router** (`src/router/index.ts`): rota `support` pública, lazy.

**Link no rodapé** (`LandingView.vue`): "Preciso de ajuda" com ícone de interrogação ao lado do copyright, leva pra `/support`.

**i18n** (`subscription.cpf_prompt.*` não tem nada a ver — só pra distinguir): namespace `support.*` em pt-BR/en/es: `title`, `subtitle`, `form.*` (name, name_placeholder, email, title_field, title_placeholder, description, description_placeholder), `submit`, `sending`, `cancel`, `sent_title`, `sent_subtitle`, `back_to_landing`, `errors.{validation, rate_limited, unknown}`.

## Efeitos colaterais

- **Mail** enviado via Resend (queue `mail`) em prod; via MailHog (`http://localhost:8025`) em dev.
- Sem persistência em DB (escolha consciente: e-mail já é o canal de armazenamento). Se quiser histórico no admin, adicionar tabela `support_messages` depois.

## Pontos de atenção

- **`replyTo` é crítico.** Sem ele, admin responde pro endereço SMTP de saída (no-reply), não pro usuário. O Reply-To é o que o cliente de e-mail mostra ao clicar "Responder".
- **5/hora por IP** é generoso pra suporte real mas bloqueia spam de bots. Se virar problema, baixar pra 2/hora ou adicionar CAPTCHA.
- **Mailable property naming:** `subjectLine`/`messageBody` em vez de `subject`/`body` por causa do conflito readonly vs non-readonly com `Illuminate\Mail\Mailable`. Documentado no próprio arquivo.
- **Trocar destinatário em prod:** mudar `SUPPORT_EMAIL` em `infra/secrets/backend.env` e restart. Sem mudar código.
- **Validação client + server:** ambos validam length mínima (3 título, 10 descrição). Cliente desabilita botão; servidor recusa com 422.
- **Falha SMTP:** `try/catch` ao redor do `Mail::send`. Loga via `Log::error('support.mail_failed', ['error' => ...])`. Retorna 500 com mensagem amigável (i18n). Usuário vê "Não conseguimos enviar agora", não stack trace.
- **Anônimo:** se user não preenche `name`, backend usa `__('support.anonymous_name')` ("Visitante"). E-mail ainda chega com `replyTo` pro endereço informado.
