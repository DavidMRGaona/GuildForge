{{-- Visitor input is printed inside one-line HTML blocks (line breaks removed or turned into <br>) so Markdown never formats it. --}}
@php($inline = static fn (string $value, string $break = ' '): string => str_replace(["\r\n", "\r", "\n"], $break, e($value)))
<x-mail::message>
# {{ __('contact.email_subject') }}

<table class="details" role="presentation" cellpadding="0" cellspacing="0"><tr><th>{{ __('contact.email_name_label') }}</th><td>{!! $inline($senderName) !!}</td></tr><tr><th>{{ __('contact.email_email_label') }}</th><td><a href="mailto:{!! $inline($senderEmail, '') !!}">{!! $inline($senderEmail, '') !!}</a></td></tr></table>

<table class="panel" width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr><td class="panel-content"><p><strong>{{ __('contact.email_message_label') }}</strong></p><p>{!! $inline($messageBody, '<br>') !!}</p></td></tr></table>

<x-slot:subcopy>
{{ __('contact.email_footer', ['app' => $mailBranding->siteName]) }}
</x-slot:subcopy>
</x-mail::message>
