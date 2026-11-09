<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="$mailBranding->siteUrl">
{{ $mailBranding->siteName }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{-- What the person can choose (mail to an account only; MailFooterDTO, core#330) --}}
@isset($mailFooter)
@if($mailFooter->isOptional())
@if($mailFooter->offersToMuteSubject())
[{{ __('mail.footer.mute_subject', ['subject' => \App\Notifications\Support\EscapesMarkdown::escape((string) $mailFooter->subjectLabel)]) }}]({{ $mailFooter->muteSubjectUrl }})

@endif
{{ __('mail.footer.optional') }} [{{ __('mail.footer.unsubscribe', ['category' => \App\Notifications\Support\EscapesMarkdown::escape((string) $mailFooter->categoryLabel)]) }}]({{ $mailFooter->unsubscribeUrl }}) · [{{ __('mail.footer.choose') }}]({{ $mailFooter->preferencesUrl }})
@elseif($mailFooter->isDigest())
{{ __('mail.footer.digest') }} @foreach($mailFooter->digestCategories as $digestCategory)[{{ __('mail.footer.unsubscribe', ['category' => \App\Notifications\Support\EscapesMarkdown::escape($digestCategory['label'])]) }}]({{ $digestCategory['unsubscribeUrl'] }}) · @endforeach[{{ __('mail.footer.choose') }}]({{ $mailFooter->preferencesUrl }})
@else
{{ __($mailFooter->mandatory ? 'mail.footer.mandatory' : 'mail.footer.without_category', ['profile' => '['.__('mail.footer.profile_link').']('.$mailFooter->preferencesUrl.')']) }}
@endif

@endisset
© {{ date('Y') }} [{{ $mailBranding->siteName }}]({{ $mailBranding->siteUrl }}). {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
