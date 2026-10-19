@props(["url"])
<tr>
<td class="header">
@php
    $logoUrl = app(\App\Application\Services\SettingsServiceInterface::class)->getEmailLogoUrl();
    $siteName = config("app.name");
@endphp
<a href="{{ $url }}" style="display: inline-block;">
@if($logoUrl)
    <img src="{{ $logoUrl }}" alt="{{ $siteName }}" style="max-height: 60px; width: auto;">
@else
    {{ $siteName }}
@endif
</a>
</td>
</tr>
