{{-- Visitor input is printed inside one-line HTML blocks (newlines as <br>) so Markdown never formats it. --}}
<x-mail::message>
# {{ __('contact.email_subject') }}

<table class="details" role="presentation" cellpadding="0" cellspacing="0"><tr><th>{{ __('contact.email_name_label') }}</th><td>{{ $senderName }}</td></tr><tr><th>{{ __('contact.email_email_label') }}</th><td><a href="mailto:{{ $senderEmail }}">{{ $senderEmail }}</a></td></tr></table>

<table class="panel" width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr><td class="panel-content"><p><strong>{{ __('contact.email_message_label') }}</strong></p><p>{!! str_replace(["\r\n", "\r", "\n"], '<br>', e($messageBody)) !!}</p></td></tr></table>

<x-slot:subcopy>
{{ __('contact.email_footer', ['app' => $mailBranding->siteName]) }}
</x-slot:subcopy>
</x-mail::message>
