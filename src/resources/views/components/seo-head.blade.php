<title>{{ $meta['fullTitle'] }}</title>
<meta name="description" content="{{ $meta['description'] }}" data-seo-server>
@if ($meta['robots'] !== null)
<meta name="robots" content="{{ $meta['robots'] }}" data-seo-server>
@endif
<link rel="canonical" href="{{ $meta['canonicalUrl'] }}" data-seo-server>
<meta property="og:type" content="{{ $meta['type'] }}" data-seo-server>
<meta property="og:site_name" content="{{ $meta['siteName'] }}" data-seo-server>
<meta property="og:locale" content="{{ $meta['locale'] }}" data-seo-server>
<meta property="og:title" content="{{ $meta['title'] }}" data-seo-server>
<meta property="og:description" content="{{ $meta['description'] }}" data-seo-server>
<meta property="og:url" content="{{ $meta['canonicalUrl'] }}" data-seo-server>
@if ($meta['image'] !== null)
<meta property="og:image" content="{{ $meta['image']['url'] }}" data-seo-server>
@if ($meta['image']['width'] !== null && $meta['image']['height'] !== null)
<meta property="og:image:width" content="{{ $meta['image']['width'] }}" data-seo-server>
<meta property="og:image:height" content="{{ $meta['image']['height'] }}" data-seo-server>
@endif
<meta property="og:image:alt" content="{{ $meta['image']['alt'] }}" data-seo-server>
@endif
<meta name="twitter:card" content="{{ ($meta['image']['isLarge'] ?? false) ? 'summary_large_image' : 'summary' }}" data-seo-server>
<meta name="twitter:title" content="{{ $meta['title'] }}" data-seo-server>
<meta name="twitter:description" content="{{ $meta['description'] }}" data-seo-server>
@if ($meta['image'] !== null)
<meta name="twitter:image" content="{{ $meta['image']['url'] }}" data-seo-server>
<meta name="twitter:image:alt" content="{{ $meta['image']['alt'] }}" data-seo-server>
@endif
@foreach ($meta['extraMeta'] as $tag)
<meta property="{{ $tag['property'] }}" content="{{ $tag['content'] }}" data-seo-server>
@endforeach
@foreach ($jsonLd as $json)
<script type="application/ld+json" data-seo-server>{!! $json !!}</script>
@endforeach
