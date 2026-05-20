<x-mail::message>
# Nova mensagem de suporte

**De:** {{ $senderName }} &lt;{{ $senderEmail }}&gt;

**Assunto:** {{ $subjectLine }}

---

{!! nl2br(e($messageBody)) !!}

---

Pra responder, basta usar o botão "Responder" no seu cliente de e-mail — vai direto pro usuário ({{ $senderEmail }}).

— {{ config('app.name') }}
</x-mail::message>
