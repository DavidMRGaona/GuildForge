<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="display-timezone" content="{{ config('app.display_timezone') }}">

        {{-- Picks the theme before the first paint, with the same precedence as useAppStore.initTheme(),
             which only runs once Vue has mounted: without it a full page load in dark mode flashes light --}}
        <script data-theme-boot>
            (function () {
                var darkByDefault = @js((bool) ($page['props']['theme']['darkModeDefault'] ?? false));
                var mode = null;
                try {
                    mode = window.localStorage.getItem('themeMode');
                } catch (e) {}
                if (mode !== 'light' && mode !== 'dark' && mode !== 'system') {
                    mode = darkByDefault ? 'dark' : 'system';
                }
                var dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>

        <x-seo-head :seo="$page['props']['seo'] ?? null" />

        {{-- Favicons: the site's own when configured; useFavicons.ts swaps them with the theme --}}
        <x-site-favicons />

        {{-- The site palette and font variables for every page, with or without a layout (core#394); the
             font files come with app.css (core#557), Inter when the settings cannot be read --}}
        <x-site-theme :theme="$page['props']['theme'] ?? null" />

        {{-- Import map for dynamic module loading (resolves vue, pinia, etc.) --}}
        <x-module-import-map />

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-page text-base-primary">
        @inertia
    </body>
</html>