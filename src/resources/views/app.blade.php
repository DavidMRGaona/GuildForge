<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <x-seo-head :seo="$page['props']['seo'] ?? null" />

        {{-- Favicons: the site's own when configured; useFavicons.ts swaps them with the theme --}}
        <x-site-favicons />

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|playfair-display:400,700" rel="stylesheet" />

        {{-- Import map for dynamic module loading (resolves vue, pinia, etc.) --}}
        <x-module-import-map />

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-page text-base-primary">
        @inertia
    </body>
</html>