<x-mail::message>
# {{ __('mail.test.heading') }}

{{ __('mail.test.intro', ['app' => $mailBranding->siteName]) }}

**{{ __('mail.test.driver') }}**: {{ $driver }}<br>
**{{ __('mail.test.date') }}**: {{ $timestamp }}

{{ __('mail.test.outro') }}

{{ __('Regards,') }}<br>
{{ $mailBranding->siteName }}
</x-mail::message>
