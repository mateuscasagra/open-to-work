<x-mail::message>
# {{ __('auth.reset.email_greeting', ['name' => $name]) }}

{{ __('auth.reset.email_intro') }}

<x-mail::button :url="$resetUrl">
{{ __('auth.reset.email_button') }}
</x-mail::button>

{{ __('auth.reset.email_expires', ['minutes' => $expiresInMinutes]) }}

{{ __('auth.reset.email_ignore') }}

{{ __('auth.reset.email_thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
