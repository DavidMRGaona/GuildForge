<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Favicons (managed dynamically by useFavicons.ts based on app theme) --}}
        <link rel="icon" type="image/png" sizes="32x32" href="/favicons/light/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicons/light/favicon-16x16.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/favicons/light/apple-touch-icon.png">
        <link rel="shortcut icon" href="/favicons/light/favicon.ico">
        <link rel="manifest" href="/favicons/light/site.webmanifest">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|playfair-display:400,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900">
        @inertia
    </body>
</html>