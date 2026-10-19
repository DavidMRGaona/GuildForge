<x-mail::message>
# {{ __('mail.test.heading') }}

{{ __('mail.test.intro', ['app' => config('app.name')]) }}

**{{ __('mail.test.driver') }}**: {{ $driver }}<br>
**{{ __('mail.test.date') }}**: {{ $timestamp }}

{{ __('mail.test.outro') }}

{{ __('Regards,') }}<br>
{{ config('app.name') }}
</x-mail::message>
