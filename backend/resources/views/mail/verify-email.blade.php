<x-mail::message>
# {{ __('auth.verify.email_greeting', ['name' => $name]) }}

{{ __('auth.verify.email_intro') }}

<x-mail::panel>
<p style="font-size: 28px; letter-spacing: 0.4em; font-weight: 700; text-align: center; margin: 0;">{{ $code }}</p>
</x-mail::panel>

{{ __('auth.verify.email_expires', ['minutes' => $expiresInMinutes]) }}

{{ __('auth.verify.email_ignore') }}

{{ __('auth.verify.email_thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
