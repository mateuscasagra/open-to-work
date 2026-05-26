<x-mail::message>
# {{ __('subscription.mail.canceled.greeting', ['name' => $name]) }}

{{ __('subscription.mail.canceled.intro') }}

@if ($accessUntil)
{{ __('subscription.mail.canceled.access_until', ['date' => $accessUntil]) }}
@else
{{ __('subscription.mail.canceled.access_ended') }}
@endif

<x-mail::button :url="$actionUrl">
{{ __('subscription.mail.canceled.button') }}
</x-mail::button>

{{ __('subscription.mail.canceled.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
