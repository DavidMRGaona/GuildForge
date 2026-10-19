@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if($mailBranding->logoUrl)
<img src="{{ $mailBranding->logoUrl }}" alt="{{ $mailBranding->siteName }}" style="max-height: 64px; width: auto;">
@else
{{ $mailBranding->siteName }}
@endif
</a>
</td>
</tr>
