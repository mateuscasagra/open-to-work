<x-mail::message>
# {{ __('subscription.mail.confirmed.greeting', ['name' => $name]) }}

{{ __('subscription.mail.confirmed.intro') }}

@if ($renewsAt !== '')
{{ __('subscription.mail.confirmed.renews', ['date' => $renewsAt]) }}
@endif

<x-mail::button :url="$actionUrl">
{{ __('subscription.mail.confirmed.button') }}
</x-mail::button>

{{ __('subscription.mail.confirmed.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
