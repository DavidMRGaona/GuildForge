@if ($fontsUrl !== null)
<link rel="stylesheet" href="{{ $fontsUrl }}" data-site-fonts>
@endif
@if ($css !== '')
<style data-site-theme>
{!! $css !!}
</style>
@endif
